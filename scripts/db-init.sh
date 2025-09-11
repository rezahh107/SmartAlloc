#!/usr/bin/env bash
set -euo pipefail

log(){ echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) [db-init] $*"; }

DB_HOST="${MYSQL_HOST:-127.0.0.1}"
DB_PORT="${MYSQL_PORT:-3306}"
DB_NAME="${MYSQL_DATABASE:-wordpress}"
DB_USER="${MYSQL_USER:-wp}"
DB_PASS="${MYSQL_PASSWORD:-password}"
ROOT_PASS="${MYSQL_ROOT_PASSWORD:-root}"

log "Initializing database"

mysql -h "$DB_HOST" -P "$DB_PORT" -uroot -p"$ROOT_PASS" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` \
  DEFAULT CHARACTER SET utf8mb4 \
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
FLUSH PRIVILEGES;
SQL

log "Database initialization complete"
