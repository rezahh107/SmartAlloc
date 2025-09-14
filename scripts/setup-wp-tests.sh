#!/usr/bin/env bash
set -euo pipefail

DB_NAME=${1:-wp_test}
DB_USER=${2:-root}
DB_PASS=${3:-root}
DB_HOST=${4:-127.0.0.1}
WP_VERSION=${5:-latest}

WP_CORE_DIR="${WP_CORE_DIR:-$(pwd)/wordpress}"
WP_TESTS_DIR="${WP_TESTS_DIR:-$(pwd)/wordpress-tests-lib}"

download() {
  local url="$1"
  local dest="$2"
  if command -v curl >/dev/null 2>&1; then
    curl -sSL "$url" -o "$dest"
  else
    wget -q "$url" -O "$dest"
  fi
}

# WordPress core
if [ ! -f "$WP_CORE_DIR/wp-settings.php" ]; then
  mkdir -p "$WP_CORE_DIR"
  tarball="/tmp/wordpress.tar.gz"
  download "https://wordpress.org/${WP_VERSION}.tar.gz" "$tarball"
  tar --strip-components=1 -zxf "$tarball" -C "$WP_CORE_DIR"
fi

# Minimal test suite (optional)
if [ ! -d "$WP_TESTS_DIR" ]; then
  mkdir -p "$WP_TESTS_DIR"
  tarball="/tmp/wordpress-develop.tar.gz"
  download "https://github.com/WordPress/wordpress-develop/archive/refs/heads/master.tar.gz" "$tarball"
  tar --strip-components=3 -zxf "$tarball" -C "$WP_TESTS_DIR" wordpress-develop-master/tests/phpunit/includes wordpress-develop-master/tests/phpunit/data >/dev/null 2>&1 || true
fi

# Create database if it doesn't exist
mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" --force >/dev/null 2>&1 || true
