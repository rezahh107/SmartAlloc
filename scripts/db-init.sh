#!/usr/bin/env bash
set -euo pipefail

# Database initialization script (idempotent)
DB_HOST="${WP_DB_HOST:-127.0.0.1}"
DB_PORT="${WP_DB_PORT:-3306}"
DB_NAME="${WP_DB_NAME:-wordpress_tests}"
DB_USER="${WP_DB_USER:-wp_user}"
DB_PASS="${WP_DB_PASS:-wp_pass}"
ROOT_PASS="${MYSQL_ROOT_PASSWORD:-root}"

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Initializing database..."

mysql -h "$DB_HOST" -P "$DB_PORT" -uroot -p"$ROOT_PASS" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` \
  DEFAULT CHARACTER SET utf8mb4 \
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
FLUSH PRIVILEGES;

-- Verify setup
SELECT CONCAT('Database ready: ', DATABASE()) AS status \
FROM DUAL \
WHERE DATABASE() = '$DB_NAME';
SQL

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Database initialization complete"
