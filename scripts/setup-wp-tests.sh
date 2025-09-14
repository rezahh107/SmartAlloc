#!/usr/bin/env bash
set -euo pipefail

DB_NAME=${1:-wp_test}
DB_USER=${2:-root}
DB_PASS=${3:-root}
DB_HOST=${4:-127.0.0.1}
WP_VERSION=${5:-latest}
WP_PATH=${WP_PATH:-$(pwd)/wordpress}

# Use wp-phpunit setup helper if available
if [ -x vendor/bin/wp-phpunit-setup ]; then
  vendor/bin/wp-phpunit-setup "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION"
else
  echo "wp-phpunit-setup not found; skipping WordPress install" >&2
fi

export WP_PATH
