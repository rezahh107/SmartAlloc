#!/usr/bin/env bash
set -euo pipefail

composer dump-autoload -o

SMARTALLOC_TESTS=1 vendor/bin/phpunit --testsuite Unit
SMARTALLOC_TESTS=1 vendor/bin/phpunit --testsuite Integration
SMARTALLOC_TESTS=1 vendor/bin/phpunit --testsuite REST

# Apply and verify locally:
#   git checkout -b feat/sa-ctx-002
#   git apply <(cat <<'PATCH'
#   ... (paste diffs returned by this prompt)
#   PATCH
#   )
#   bash scripts/dev-verify.sh
# Commit and push if green:
#   git add -A && git commit -m "SA-CTX-002: test harness & REST wiring fixes for context-aware allocation"
#   git push origin feat/sa-ctx-002
