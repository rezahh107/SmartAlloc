#!/usr/bin/env bash
set -euo pipefail

echo "== SmartAlloc Docker Pre-push =="

# Detect Docker Compose invocation
if docker compose version >/dev/null 2>&1; then
  DC=(docker compose -p smartalloc)
elif command -v docker-compose >/dev/null 2>&1; then
  DC=(docker-compose -p smartalloc)
else
  echo "ERROR: Docker Compose not found. Install Docker Desktop / Compose." >&2
  exit 1
fi

echo "[1/4] Bringing up services (db, wordpress)"
"${DC[@]}" up -d

echo "[2/4] Ensuring Composer deps are installed (optimized)"
"${DC[@]}" exec -T wordpress bash -lc '
  set -euo pipefail
  cd /var/www/html/wp-content/plugins/smart-alloc
  export COMPOSER_MEMORY_LIMIT=-1
  composer install -o || true
'

echo "[3/4] Running tests"
"${DC[@]}" exec -T wordpress bash -lc '
  set -euo pipefail
  cd /var/www/html/wp-content/plugins/smart-alloc
  echo " - setup wp tests (idempotent)" && composer -q setup:wp-tests || true
  echo " - unit (fast, no coverage)" && composer -q test:debug || echo "   ⚠ unit tests failed (non-blocking)"
  echo " - integration" && composer -q test:integration || echo "   ⚠ integration tests failed (non-blocking)"
  echo " - foundation" && composer -q test:foundation || echo "   ⚠ foundation tests failed (non-blocking)"
'

echo "[4/4] Patch Guard (sanity before push)"
"${DC[@]}" exec -T wordpress bash -lc '
  set -euo pipefail
  export HOME=/tmp
  cd /var/www/html/wp-content/plugins/smart-alloc
  git -c safe.directory=/var/www/html/wp-content/plugins/smart-alloc config --global --add safe.directory /var/www/html/wp-content/plugins/smart-alloc || true
  bash scripts/patch-guard-check.sh
'

echo "✅ Pre-push checks completed"
