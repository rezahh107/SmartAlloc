#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html/wp-content/plugins/smart-alloc

# Export variables from .env if present
if [ -f ./.env ]; then
  set -a
  . ./.env
  set +a
fi

# Best-effort composer helper if available (some envs may not have this script)
if [ -x vendor/bin/wp-phpunit-setup ]; then
  vendor/bin/wp-phpunit-setup || true
fi

# Install WP tests DB and config using project helper
DB_NAME="${MYSQL_DATABASE:-wordpress}"
DB_USER="${MYSQL_USER:-root}"
DB_PASS="${MYSQL_PASSWORD:-}"
DB_HOST="${MYSQL_HOST:-db}"

if [ -x bin/install-wp-tests.sh ]; then
  bash bin/install-wp-tests.sh "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST"
else
  echo "Missing bin/install-wp-tests.sh" >&2
  exit 2
fi

echo "WP tests setup complete."
