#!/usr/bin/env bash
set -euo pipefail

echo "[init] Starting SmartAlloc container init..."

WP_VERSION="${WP_VERSION:-6.6.2}"
DB_HOST="${WP_TESTS_DB_HOST:-db}"
DB_NAME="${WP_TESTS_DB_NAME:-wordpress_tests}"
DB_USER="${WP_TESTS_DB_USER:-root}"
DB_PASS="${WP_TESTS_DB_PASS:-root}"

echo "[init] WP_VERSION=${WP_VERSION}"
echo "[init] DB=${DB_USER}@${DB_HOST}/${DB_NAME}"

echo "[init] Waiting for database to be ready..."
for i in {1..60}; do
  if mysqladmin ping -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" --silent >/dev/null 2>&1; then
    echo "[init] Database is up."
    break
  fi
  sleep 1
done

if ! mysqladmin ping -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" --silent >/dev/null 2>&1; then
  echo "[init] ERROR: Database did not become ready in time." >&2
  exit 1
fi

echo "[init] Installing Composer dependencies..."
composer install --no-interaction --prefer-dist

echo "[init] Setting up WordPress PHPUnit (WP_VERSION=${WP_VERSION})..."
composer setup:wp-tests || true

echo "[init] Validating test environment..."
composer validate:test-env || true

echo "[init] Done. You can now run tests: vendor/bin/phpunit -v"

