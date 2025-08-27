#!/usr/bin/env bash
set -euo pipefail
AI_CTX="ai_context.json"
test -s "$AI_CTX" || echo '{"decisions":[]}' > "$AI_CTX"
jq empty "$AI_CTX"
SEC="$(jq -r '.current_scores.security // 0' "$AI_CTX")"
WGT="$(jq -r '.current_scores.weighted_percent // 0' "$AI_CTX")"
PHPCS_FAILS="$(jq -r '.current_scores.readability // 0' "$AI_CTX" | awk '{print 0}')"
TEST_FAILS="${TEST_FAILS:-0}"
if (( $(printf '%.0f' "$SEC") < 20 )) || (( $(printf '%.0f' "$WGT") < 85 )); then
  jq -n --argjson sec "${SEC:-0}" \
        --argjson phpcs "${PHPCS_FAILS:-0}" \
        --argjson tests "${TEST_FAILS:-0}" \
        '{ci_failure:{security_score:$sec, phpcs_errors:$phpcs, test_failures:$tests}}' \
  > .ci_failure.tmp
  # merge into ai_context.json (non-destructive)
  jq -s '.[0] * .[1]' "$AI_CTX" .ci_failure.tmp > ai_context.tmp && mv ai_context.tmp "$AI_CTX"
  rm -f .ci_failure.tmp
fi

