#!/bin/bash
# Run WordPress Plugin Check using a temporary database

set -euo pipefail

PLUGIN_DIR="${1:-$(pwd)}"
PLUGIN_DIR="$(cd "$PLUGIN_DIR" && pwd)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Setup database and WordPress configuration
source "$SCRIPT_DIR/setup-wp-test-db.sh"

cleanup() {
  echo "Cleaning up temporary environment"
  mysql -h"$DB_HOST" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -e "DROP DATABASE IF EXISTS \`$WP_TEST_DB_NAME\`;" >/dev/null 2>&1 || true
  rm -rf "$WP_TEST_DIR"
}
trap cleanup EXIT

# Install WordPress core
wp core install --allow-root --path="$WP_TEST_DIR" \
  --url="http://localhost" --title="Test Site" \
  --admin_user=admin --admin_password=password --admin_email=test@example.com \
  --skip-email >/dev/null

# Run plugin check
echo "Running plugin check for: $PLUGIN_DIR"
WP_CLI_PACKAGES_DIR="$WP_CLI_PACKAGES_DIR" php -d memory_limit=512M "$(command -v wp)" plugin check "$PLUGIN_DIR" --allow-root --path="$WP_TEST_DIR" --format=table
