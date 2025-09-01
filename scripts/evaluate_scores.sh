#!/usr/bin/env bash
set -euo pipefail
AI_CTX="ai_context.json"
test -s "$AI_CTX" || echo '{"decisions":[]}' > "$AI_CTX"
jq empty "$AI_CTX"
SEC="$(jq -r '.current_scores.security // 0' "$AI_CTX")"
WGT="$(jq -r '.current_scores.weighted_percent // 0' "$AI_CTX")"
LOGIC="$(jq -r '.current_scores.logic // 0' "$AI_CTX")"
PERF="$(jq -r '.current_scores.performance // 0' "$AI_CTX")"
READ="$(jq -r '.current_scores.readability // 0' "$AI_CTX")"
GOAL="$(jq -r '.current_scores.goal // 0' "$AI_CTX")"
PHPCS_JSON="$(vendor/bin/phpcs --standard=WordPress --extensions=php src tests --report=json 2>/dev/null || true)"
PHPCS_FAILS="$(jq -r '.totals.errors + .totals.warnings' <<<"$PHPCS_JSON" 2>/dev/null || echo 0)"
tmp="$AI_CTX.tmp"
jq --argjson phpcs "$PHPCS_FAILS" '.phpcs_errors=$phpcs' "$AI_CTX" > "$tmp" && mv "$tmp" "$AI_CTX"
TEST_FAILS="${TEST_FAILS:-0}"
LOGIC_MIN="${LOGIC_MIN:-18}"
PERFORMANCE_MIN="${PERFORMANCE_MIN:-19}"
READABILITY_MIN="${READABILITY_MIN:-19}"
GOAL_MIN="${GOAL_MIN:-18}"
if (( $(printf '%.0f' "$SEC") < 20 )) || \
   (( $(printf '%.0f' "$WGT") < 85 )) || \
   (( $(printf '%.0f' "$LOGIC") < LOGIC_MIN )) || \
   (( $(printf '%.0f' "$PERF") < PERFORMANCE_MIN )) || \
   (( $(printf '%.0f' "$READ") < READABILITY_MIN )) || \
   (( $(printf '%.0f' "$GOAL") < GOAL_MIN )); then
  jq -n --argjson sec "${SEC:-0}" \
        --argjson phpcs "${PHPCS_FAILS:-0}" \
        --argjson tests "${TEST_FAILS:-0}" \
        --argjson logic "${LOGIC:-0}" --argjson logic_min "$LOGIC_MIN" \
        --argjson perf "${PERF:-0}" --argjson perf_min "$PERFORMANCE_MIN" \
        --argjson read "${READ:-0}" --argjson read_min "$READABILITY_MIN" \
        --argjson goal "${GOAL:-0}" --argjson goal_min "$GOAL_MIN" \
        '{ci_failure:{security_score:$sec, phpcs_errors:$phpcs, test_failures:$tests, logic:{score:$logic,min:$logic_min}, performance:{score:$perf,min:$perf_min}, readability:{score:$read,min:$read_min}, goal:{score:$goal,min:$goal_min}}}' \
  > .ci_failure.tmp
  # merge into ai_context.json (non-destructive)
  jq -s '.[0] * .[1]' "$AI_CTX" .ci_failure.tmp > ai_context.tmp && mv ai_context.tmp "$AI_CTX"
  rm -f .ci_failure.tmp
  exit 1
fi

