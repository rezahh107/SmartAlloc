#!/usr/bin/env bash
# scripts/update_state.sh — 5D scoring for SmartAlloc
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC_DIR="${SRC_DIR:-$ROOT/src}"
TESTS_DIR="${TESTS_DIR:-$ROOT/tests}"
FEATURES_MD="${FEATURES_MD:-$ROOT/FEATURES.md}"
AI_CTX="${AI_CTX:-$ROOT/ai_context.json}"
phpstan_cmd="${PHPSTAN_CMD:-$ROOT/vendor/bin/phpstan}"
phpcs_cmd="${PHPCS_CMD:-$ROOT/vendor/bin/phpcs}"
UTC_NOW="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

if ! command -v jq >/dev/null 2>&1 || ! command -v bc >/dev/null 2>&1; then
  echo "Missing required tools: jq and bc. Please install them." >&2
  exit 2
fi

score_part() { # clamp to 0..max
  local v="$1" max="$2"
  if [ "$v" -lt 0 ]; then echo 0
  elif [ "$v" -gt "$max" ]; then echo "$max"
  else echo "$v"; fi
}

# ---------- Static Analysis (Security & Logic) ----------
analysis_json='{"totals":{"errors":0}}'
if [ -x "$phpstan_cmd" ]; then
  tmp_cfg=$(mktemp --suffix=.neon)
  echo '' > "$tmp_cfg"
  set +e
  analysis_json=$("$phpstan_cmd" analyse --no-progress --error-format=json --level=1 --configuration="$tmp_cfg" "$SRC_DIR" 2>/dev/null)
  set -e
  rm -f "$tmp_cfg"
fi
analysis_errors=$(echo "$analysis_json" | jq '.totals.file_errors // 0' 2>/dev/null || echo 0)
base_score=$((25 - analysis_errors*5))
SECURITY_SCORE=$(score_part "$base_score" 25)
LOGIC_SCORE=$(score_part "$base_score" 25)

# ---------- Performance (25 = 10 + 15) ----------
db_q=$( (grep -R -E "\$wpdb->(get_var|get_row|get_results|query)\(" "$SRC_DIR" 2>/dev/null || true) | wc -l | tr -d ' ' )
if   [ "$db_q" -le 5 ];  then db_score=10
elif [ "$db_q" -le 15 ]; then db_score=8
elif [ "$db_q" -le 30 ]; then db_score=5
else db_score=3; fi
cache_cnt=$( (grep -R -E "wp_cache_(get|set)|get_transient|set_transient" "$SRC_DIR" 2>/dev/null || true) | wc -l | tr -d ' ' )
cache_score=$(( cache_cnt > 0 ? 15 : 0 ))
PERF_SCORE=$(echo "$db_score + $cache_score" | bc)

# ---------- Readability (25) ----------
READABILITY_SCORE=20
if [ -x "$phpcs_cmd" ]; then
  sum_json="$ROOT/.phpcs-sum.json"
  "$phpcs_cmd" -q --standard="$ROOT/phpcs.xml" --report=json > "$sum_json" || true
  warn=$(jq -r '.totals.warnings // 0' "$sum_json" 2>/dev/null || echo 0)
  # Map warnings to 25: 0→25, 1..5→22..18, 6..10→17..13, >10→12
  if [ "$warn" -eq 0 ]; then READABILITY_SCORE=25
  elif [ "$warn" -le 5 ]; then READABILITY_SCORE=$((25 - warn*1))
  elif [ "$warn" -le 10 ]; then READABILITY_SCORE=$((20 - (warn-5)))
  else READABILITY_SCORE=12; fi
fi

# ---------- Goal Achievement (25 = 15 + 10) ----------
req_score=0 integ_score=0
[ -d "$SRC_DIR" ]   && [ "$(ls -A "$SRC_DIR" 2>/dev/null | wc -l)" -gt 0 ] && req_score=15
[ -d "$TESTS_DIR" ] && [ "$(find "$TESTS_DIR" -name '*.php' | wc -l)" -gt 0 ] && integ_score=10
GOAL_SCORE=$(echo "$req_score + $integ_score" | bc)

# ---------- Red Flags ----------
flags=()
grep -R "\$_POST\|\$_GET" "$SRC_DIR" 2>/dev/null | grep -v "sanitize_\|filter_input" >/dev/null 2>&1 && flags+=("Direct superglobal access (-10)")
grep -R -E "[^p]query\s*\(" "$SRC_DIR" 2>/dev/null | grep -v "prepare\(" >/dev/null 2>&1 && flags+=("Raw SQL without prepare (-10)")
rf_deduction=0
for f in "${flags[@]:-}"; do rf_deduction=$((rf_deduction+10)); done
TOTAL_SCORE_INT=$(printf "%0.f" "$(echo "$SECURITY_SCORE + $LOGIC_SCORE + $PERF_SCORE + $READABILITY_SCORE + $GOAL_SCORE - $rf_deduction" | bc)")
TOTAL_SCORE_INT=$(score_part "$TOTAL_SCORE_INT" 125)

# Weighted Average: (Sec×2 + Log×2 + Perf×1 + Read×1 + Goal×2) / 200
weighted=$(echo "scale=2; ( ($SECURITY_SCORE*2)+($LOGIC_SCORE*2)+($PERF_SCORE)+($READABILITY_SCORE)+($GOAL_SCORE*2) ) / 200 * 100" | bc)

# ---------- Update ai_context.json ----------
if [ ! -f "$AI_CTX" ]; then echo '{"decisions":[]}' > "$AI_CTX"; fi
jq empty "$AI_CTX"
tmp="$AI_CTX.tmp"
 flag_json="[]"
 if [ ${#flags[@]} -gt 0 ]; then
   flag_json=$(printf '%s\n' "${flags[@]}" | jq -R . | jq -s .)
 fi
 jq --argjson sec "$SECURITY_SCORE" \
   --argjson log "$LOGIC_SCORE" \
   --argjson perf "$PERF_SCORE" \
   --argjson read "$READABILITY_SCORE" \
   --argjson goal "$GOAL_SCORE" \
   --arg total "$TOTAL_SCORE_INT" \
   --arg weighted "$weighted" \
   --argjson flags "$flag_json" \
   --arg now "$UTC_NOW" \
   '
    .current_scores = {
      security:$sec, logic:$log, performance:$perf, readability:$read, goal:$goal,
      total: ($total|tonumber), weighted_percent: ($weighted|tonumber), red_flags: $flags
    }
   | .last_updated_utc = $now
   ' "$AI_CTX" > "$tmp" && mv "$tmp" "$AI_CTX"

# ---------- Write FEATURES.md (summary block at top) ----------
{
  echo "# Feature Status Dashboard"
  echo
  perc=$(echo "$TOTAL_SCORE_INT*100/125" | bc)
  echo "## 📊 Current Project Score: ${TOTAL_SCORE_INT}/125 (${perc}%)"
  echo
  echo "### **📊 Detailed Validation Score**"
  printf "🔒 **Security Score**: %.2f/25\n" "$SECURITY_SCORE"
  printf "🧠 **Logic Score**: %.2f/25\n"    "$LOGIC_SCORE"
  printf "⚡ **Performance Score**: %.2f/25\n" "$PERF_SCORE"
  printf "📖 **Readability Score**: %.2f/25\n" "$READABILITY_SCORE"
  printf "🎯 **Goal Achievement**: %.2f/25\n"  "$GOAL_SCORE"
  echo
  echo "**🏆 Total Score**: ${TOTAL_SCORE_INT}/125"
  printf "**📈 Weighted Average**: %.2f%%\n" "$weighted"
  if [ "${#flags[@]}" -gt 0 ]; then
    echo
    echo "### ⛔ Red Flags:"
    for f in "${flags[@]}"; do echo "- $f"; done
  else
    echo
    echo "### ✅ No Red Flags Detected"
  fi
  echo
  echo "---"
  echo "Last Updated (UTC): ${UTC_NOW}"
} > "$FEATURES_MD"

echo "✅ 5D scoring completed."
