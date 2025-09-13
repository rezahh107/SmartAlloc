#!/usr/bin/env bash
# Run Playwright E2E only when UI/editor-related files changed.
# Non-blocking by default; exits 0 even on failures to keep push fast.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 0

# Determine BASE for diff (prefer develop, else previous commit)
BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
BASE=$(git merge-base origin/develop HEAD 2>/dev/null || git rev-parse HEAD~1 2>/dev/null || echo "")

FILTER_EXCLUDE='^(vendor/|node_modules/|dist/|build/|assets/dist/|languages/.*\.(mo|po)$|.*\.min\.(js|css)$|.*\.bundle\.js$)'
CHANGED=$( { git diff --name-only "$BASE...HEAD" 2>/dev/null | grep -Ev "$FILTER_EXCLUDE" || true; } )

needs_e2e=0
if printf '%s\n' "$CHANGED" | grep -Eq '^(src/Admin/|includes/Admin/|templates/|assets/|e2e/|tests/e2e/|.*\.(css|scss|js|ts|tsx)$)'; then
  needs_e2e=1
fi

if [ "$needs_e2e" != "1" ]; then
  echo "[skip:e2e] No UI/editor-related changes detected"
  exit 0
fi

if ! command -v npx >/dev/null 2>&1; then
  echo "[skip:e2e] npx not found"
  exit 0
fi

# Prefer explicit WP_BASE_URL from env; else leave defaults from config
export E2E=${E2E:-1}
echo "[run:e2e] Detected relevant changes; running Playwright smoke tests"

set +e
npx --yes playwright install --with-deps >/dev/null 2>&1 || true
npx playwright test e2e/tests/editor-smoke.spec.ts || echo "[warn:e2e] editor-smoke failed"
E2E=1 npx playwright test tests/e2e/smoke.spec.ts || echo "[warn:e2e] smoke failed"
set -e

echo "[done:e2e]"
exit 0

