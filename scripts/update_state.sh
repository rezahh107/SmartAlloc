#!/usr/bin/env bash
# scripts/update_state.sh — 5D scoring for SmartAlloc
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC_DIR="${SRC_DIR:-$ROOT/src}"
TESTS_DIR="${TESTS_DIR:-$ROOT/tests}"
FEATURES_MD="$ROOT/FEATURES.md"
AI_CTX="$ROOT/ai_context.json"
phpcs_cmd="${PHPCS_CMD:-$ROOT/vendor/bin/phpcs}"
UTC_NOW="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

if ! command -v jq >/dev/null 2>&1 || ! command -v bc >/dev/null 2>&1; then
  echo "Missing required tools: jq and bc. Please install them." >&2
  exit 2
fi

# Soft-fix coding style before scoring
if [ -x "${ROOT}/vendor/bin/phpcbf" ]; then
  "${ROOT}/vendor/bin/phpcbf" -q --standard="$ROOT/phpcs.xml" src || true
fi

score_part() { # clamp to 0..max
  local v="$1" max="$2"
  if [ "$v" -lt 0 ]; then echo 0
  elif [ "$v" -gt "$max" ]; then echo "$max"
  else echo "$v"; fi
}

# ---------- Security (25 = 4×6.25) ----------
sec_nonce=0 sec_prepare=0 sec_utc=0 sec_typed=0
if [ -d "$SRC_DIR" ]; then
  grep -R -E "wp_verify_nonce|check_admin_referer" "$SRC_DIR" >/dev/null 2>&1 && sec_nonce=1
  grep -R -E "DbSafe::mustPrepare|\$wpdb->prepare\(|->prepare\(" "$SRC_DIR" >/dev/null 2>&1 && sec_prepare=1
  grep -R -E "current_time\s*\(\s*['\"]mysql['\"]\s*,\s*1\s*\)" "$SRC_DIR" >/dev/null 2>&1 && sec_utc=1
  grep -R -E "throw\s+new\s+[A-Z][A-Za-z0-9_]*Exception" "$SRC_DIR" >/dev/null 2>&1 && sec_typed=1
fi
SECURITY_SCORE=$(echo "scale=2; ($sec_nonce+$sec_prepare+$sec_utc+$sec_typed)*6.25" | bc)

# ---------- Logic (25 = 8+8+9) ----------
# Heuristics: edge cases (if/elseif/default), error handling (try/catch), input validation (sanitize_/esc_/filter_var)
edge_cnt=$( (grep -R -E "elseif|else if|default\s*:" "$SRC_DIR" 2>/dev/null || true) | wc -l | tr -d ' ' )
edge_score=$(( edge_cnt >= 3 ? 8 : edge_cnt*3 )) # 0..8
try_cnt=$( (grep -R -E "\btry\b" "$SRC_DIR" 2>/dev/null || true) | wc -l | tr -d ' ' )
err_score=$(( try_cnt > 0 ? 8 : 0 ))            # 0/8
val_cnt=$( (grep -R -E "sanitize_|esc_|filter_var\(" "$SRC_DIR" 2>/dev/null || true) | wc -l | tr -d ' ' )
val_score=$(( val_cnt >= 3 ? 9 : val_cnt*3 ))   # 0..9
LOGIC_SCORE=$(echo "$edge_score+$err_score+$val_score" | bc)

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
tmp="$AI_CTX.tmp"
jq --argjson sec "$SECURITY_SCORE" \
   --argjson log "$LOGIC_SCORE" \
   --argjson perf "$PERF_SCORE" \
   --argjson read "$READABILITY_SCORE" \
   --argjson goal "$GOAL_SCORE" \
   --arg total "$TOTAL_SCORE_INT" \
   --arg weighted "$weighted" \
   --argjson flags "$(printf '%s\n' "${flags[@]:-}" | jq -R . | jq -s .)" \
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
