#!/usr/bin/env bash
set -euo pipefail

echo "== SmartAlloc Docker Pre-commit =="

# Detect Docker Compose invocation
if docker compose version >/dev/null 2>&1; then
  DC=(docker compose -p smartalloc)
elif command -v docker-compose >/dev/null 2>&1; then
  DC=(docker-compose -p smartalloc)
else
  echo "ERROR: Docker Compose not found. Install Docker Desktop / Compose." >&2
  exit 1
fi

echo "[1/5] Bringing up services (db, wordpress)"
"${DC[@]}" up -d

echo "[2/5] Ensuring Composer deps are installed (optimized)"
"${DC[@]}" exec -T wordpress bash -lc '
  set -euo pipefail
  cd /var/www/html/wp-content/plugins/smart-alloc
  export COMPOSER_MEMORY_LIMIT=-1
  composer install -o || true
'

echo "[3/5] Running selective quality gates (staged PHP files)"
"${DC[@]}" exec -T wordpress bash -lc '
  set -euo pipefail
  cd /var/www/html/wp-content/plugins/smart-alloc
  composer run -q quality:selective || true
'

echo "[4/5] Running baseline check (FOUNDATION)"
"${DC[@]}" exec -T wordpress bash -lc '
  set -euo pipefail
  cd /var/www/html/wp-content/plugins/smart-alloc
  php baseline-check --current-phase=FOUNDATION
'

echo "[5/5] Running Patch Guard"
"${DC[@]}" exec -T wordpress bash -lc '
  set -euo pipefail
  export HOME=/tmp
  git -c safe.directory=/var/www/html/wp-content/plugins/smart-alloc \
      config --global --add safe.directory /var/www/html/wp-content/plugins/smart-alloc || true
  cd /var/www/html/wp-content/plugins/smart-alloc
  bash scripts/patch-guard-check.sh
'

echo "✅ All pre-commit checks completed"

