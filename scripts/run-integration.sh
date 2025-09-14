#!/usr/bin/env bash
set -euo pipefail

echo "[integration] starting…"

if ! command -v docker >/dev/null 2>&1; then
  echo "[integration] docker CLI not found; skipping."; exit 0
fi
if ! docker info >/dev/null 2>&1; then
  echo "[integration] docker daemon unreachable; skipping."; exit 0
fi
if ! docker compose version >/dev/null 2>&1; then
  echo "[integration] docker compose plugin missing; skipping."; exit 0
fi

if [[ -f "docker-compose.test.yml" ]]; then
  echo "[integration] compose up --wait…"
  docker compose -f docker-compose.test.yml up -d --wait
else
  echo "[integration] docker-compose.test.yml not found; skipping."; exit 0
fi

cleanup() {
  echo "[integration] compose down…"
  docker compose -f docker-compose.test.yml down -v || true
  echo "[integration] done."
}
trap cleanup EXIT

export WP_INTEGRATION="${WP_INTEGRATION:-1}"
export WP_PATH="${WP_PATH:-/tmp/wp}"
bash scripts/setup-wp-tests.sh

vendor/bin/phpunit --testsuite=integration -v
