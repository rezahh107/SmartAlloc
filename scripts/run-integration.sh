#!/usr/bin/env bash
set -euo pipefail

# CI mode is strict: fail if Docker/DB not available
if [[ "${CI:-}" == "true" ]]; then
  if ! command -v docker >/dev/null; then
    echo "Docker is required for integration tests in CI" >&2
    exit 1
  fi
  docker compose -f docker-compose.test.yml up -d db
  bash scripts/wait-for-db.sh 127.0.0.1 root root
else
  if ! command -v docker >/dev/null; then
    echo "Docker not available; skipping integration tests" >&2
    exit 0
  fi
  if ! docker compose -f docker-compose.test.yml up -d db; then
    echo "Docker compose failed; skipping integration tests" >&2
    exit 0
  fi
  if ! bash scripts/wait-for-db.sh 127.0.0.1 root root; then
    echo "Database not ready; skipping integration tests" >&2
    exit 0
  fi
fi

bash scripts/setup-wp-tests.sh wp_test root root 127.0.0.1 latest
export DB_HOST=127.0.0.1
export DB_USER=root
export DB_PASSWORD=root
export DB_NAME=wp_test
export WP_INTEGRATION=1

vendor/bin/phpunit --testsuite=integration
