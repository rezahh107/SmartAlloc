#!/usr/bin/env bash
set -euo pipefail
AI_CTX="ai_context.json"
# ensure AI_CTX exists with default structure
if ! test -s "$AI_CTX"; then
  echo '{"decisions":[]}' > "$AI_CTX"
fi
# if ci_failure key not present, exit successfully
if ! jq -e '.ci_failure' "$AI_CTX" >/dev/null 2>&1; then
  exit 0
fi
SEC="$(jq -r '.ci_failure.security_score // "?"' "$AI_CTX")"
PHPCS="$(jq -r '.ci_failure.phpcs_errors // "?"' "$AI_CTX")"
TESTS="$(jq -r '.ci_failure.test_failures // "?"' "$AI_CTX")"
echo "::error title=5D Gate Failed::security=${SEC}/25 phpcs=${PHPCS} tests=${TESTS}"
exit 1
