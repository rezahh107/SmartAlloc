#!/usr/bin/env bash
set -euo pipefail
command -v jq >/dev/null || { echo "jq is required" >&2; exit 1; }
DB_NAME=${1:-wordpress_test}
DB_USER=${2:-root}
DB_PASS=${3:-}
DB_HOST=${4:-localhost}
WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
[ -d "$WP_TESTS_DIR" ] || { mkdir -p "$WP_TESTS_DIR"; curl -sL https://github.com/wp-phpunit/builds/raw/gh-pages/wp-phpunit-7.tar.gz | tar -xz -C "$WP_TESTS_DIR" --strip-components=1; }
mysqladmin create "$DB_NAME" --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" || true
sed -e "s/youremptytestdbnamehere/$DB_NAME/" -e "s/yourusernamehere/$DB_USER/" -e "s/yourpasswordhere/$DB_PASS/" -e "s|localhost|$DB_HOST|" "$WP_TESTS_DIR/wp-tests-config-sample.php" > "$WP_TESTS_DIR/wp-tests-config.php"
