#!/bin/bash
# Setup WP Test Database for Plugin Check
# Creates a temporary database and prepares a WordPress environment

set -euo pipefail

# Ensure MySQL client is available
if ! command -v mysql >/dev/null 2>&1; then
  echo "MySQL client not found. Please install MySQL or MariaDB client." >&2
  exit 1
fi

# Determine environment (local vs CI/Docker)
if [ -n "${CI:-}" ] || [ -f /.dockerenv ]; then
  DB_USER="${DB_USER:-root}"
  DB_PASS="${DB_PASS:-root}"
  DB_HOST="${DB_HOST:-127.0.0.1}"
else
  DB_USER="${DB_USER:-root}"
  DB_PASS="${DB_PASS:-}"
  DB_HOST="${DB_HOST:-localhost}"
fi

# Create a unique temporary database
DB_NAME="wp_test_$(date +%s)_$RANDOM"
mysql -h"$DB_HOST" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;" || {
  echo "Unable to create database. Check MySQL credentials." >&2
  exit 1
}

# Prepare temporary WordPress directory
WP_TEST_DIR=$(mktemp -d /tmp/wp-test-XXXX)
export WP_TEST_DIR

# Install WP-CLI if missing
if ! command -v wp >/dev/null 2>&1; then
  "$(dirname "$0")/install-wp-cli.sh"
fi

# Setup packages directory and install plugin-check package
WP_CLI_PACKAGES_DIR=${WP_CLI_PACKAGES_DIR:-/tmp/wp-cli-packages}
export WP_CLI_PACKAGES_DIR
mkdir -p "$WP_CLI_PACKAGES_DIR"
if ! wp package list --allow-root | grep -q "plugin-check-command"; then
  wp package install wp-cli/plugin-check-command --allow-root >/dev/null
fi

# Download WordPress core and create configuration
wp core download --allow-root --path="$WP_TEST_DIR" >/dev/null
wp config create --allow-root --path="$WP_TEST_DIR" --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --skip-check >/dev/null

# Export database variables for caller
export WP_TEST_DB_NAME="$DB_NAME"
export DB_HOST DB_USER DB_PASS

echo "WordPress test environment ready at $WP_TEST_DIR"
echo "Database: $DB_NAME"
