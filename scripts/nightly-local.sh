#!/usr/bin/env bash
set -euo pipefail
echo "== Nightly (local-only, non-blocking) =="

ran_any=0

if command -v php >/dev/null 2>&1; then
  echo "-- Security tests (opt-in)"
  RUN_SECURITY_TESTS=${RUN_SECURITY_TESTS:-0}
  if [ "$RUN_SECURITY_TESTS" = "1" ]; then
    ran_any=1
    vendor/bin/phpunit --colors=always --testdox --filter "NonceVerification|SQLInjection" || true
  else
    echo "   skipped (set RUN_SECURITY_TESTS=1)"
  fi

  echo "-- Performance budget (opt-in)"
  RUN_PERFORMANCE_TESTS=${RUN_PERFORMANCE_TESTS:-0}
  if [ "$RUN_PERFORMANCE_TESTS" = "1" ]; then
    ran_any=1
    vendor/bin/phpunit --colors=always --filter RequestBudget || true
  else
    echo "   skipped (set RUN_PERFORMANCE_TESTS=1)"
  fi
fi

echo "-- E2E (opt-in)"
if command -v npx >/dev/null 2>&1; then
  if [ "${E2E:-0}" = "1" ]; then
    ran_any=1
    BASE_URL=${BASE_URL:-http://localhost:8889} npx playwright test || true
  else
    echo "   skipped (set E2E=1)"
  fi
else
  echo "   skipped (npx not found)"
fi

if [ "$ran_any" -eq 0 ]; then
  echo "Nothing ran; set RUN_SECURITY_TESTS=1 / RUN_PERFORMANCE_TESTS=1 / E2E=1"
fi
exit 0

