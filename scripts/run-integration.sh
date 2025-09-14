#!/usr/bin/env bash
set -euo pipefail

echo "[integration] starting…"

# 0) Docker availability
if ! command -v docker >/dev/null 2>&1; then
  echo "[integration] docker CLI not found; skipping."; exit 0
fi

# 1) Daemon readiness (local or via DOCKER_HOST)
if ! docker info >/dev/null 2>&1; then
  echo "[integration] docker daemon unreachable; skipping."; exit 0
fi

# 2) Compose presence
if ! docker compose version >/dev/null 2>&1; then
  echo "[integration] docker compose not available; skipping."; exit 0
fi

# 3) Bring up test DB stack if compose file exists
if [[ -f "docker-compose.test.yml" ]]; then
  echo "[integration] bringing up compose stack…"
  docker compose -f docker-compose.test.yml up -d --wait
else
  echo "[integration] docker-compose.test.yml not found; skipping."; exit 0
fi

# 4) Prepare WP tests (standard script)
export WP_INTEGRATION="${WP_INTEGRATION:-1}"
export WP_PATH="${WP_PATH:-/tmp/wp}"
bash scripts/setup-wp-tests.sh

# 5) Run integration suite
vendor/bin/phpunit --testsuite=integration -v

# 6) Teardown (best-effort)
echo "[integration] tearing down compose stack…"
docker compose -f docker-compose.test.yml down -v || true
echo "[integration] done."

