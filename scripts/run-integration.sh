#!/usr/bin/env bash
set -euo pipefail

# 1) اگر Docker داری، DB را بالا بیار
if command -v docker &>/dev/null; then
  docker compose -f docker-compose.test.yml up -d db
  bash scripts/wait-for-db.sh 127.0.0.1 root root
  DB_HOST="127.0.0.1"
  DB_USER="root"
  DB_PASS="root"
  DB_NAME="wp_test"
else
  # فرض: یک MySQL محلی داری
  DB_HOST="${DB_HOST:-127.0.0.1}"
  DB_USER="${DB_USER:-root}"
  DB_PASS="${DB_PASS:-root}"
  DB_NAME="${DB_NAME:-wp_test}"
  bash scripts/wait-for-db.sh "$DB_HOST" "$DB_USER" "$DB_PASS"
fi

# 2) نصب/آپدیت محیط تست وردپرس (اگر اسکریپت استاندارد را داری)
if [ -f "scripts/setup-wp-tests.sh" ]; then
  bash scripts/setup-wp-tests.sh "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" latest
fi

# Export vars for bootstrap
export DB_HOST DB_USER DB_PASS DB_NAME
export WP_PATH="$PWD/wordpress"

# 3) اجرای PHPUnit در حالت Integration
export WP_INTEGRATION=1
vendor/bin/phpunit --testsuite=integration
