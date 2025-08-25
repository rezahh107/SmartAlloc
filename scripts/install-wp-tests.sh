#!/usr/bin/env bash
set -e

if [ $# -lt 3 ]; then
  echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
  exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() { if command -v curl >/dev/null 2>&1; then curl -sSL -o "$1" "$2"; else wget -q -O "$1" "$2"; fi; }

install_wp() {
  if [ -d "$WP_CORE_DIR" ]; then return; fi
  mkdir -p "$WP_CORE_DIR"
  if [ "$WP_VERSION" = "latest" ]; then
    download $TMPDIR/wordpress.tar.gz https://wordpress.org/latest.tar.gz
  elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
    download $TMPDIR/wordpress.tar.gz https://wordpress.org/wordpress-$WP_VERSION.tar.gz
  else
    download $TMPDIR/wordpress.tar.gz https://wordpress.org/nightly-builds/wordpress-latest.tar.gz
  fi
  tar --strip-components=1 -zxmf $TMPDIR/wordpress.tar.gz -C "$WP_CORE_DIR"
}

install_test_suite() {
  mkdir -p "$WP_TESTS_DIR"
  # شامل‌ها و دیتا از develop گرفته می‌شود
  download $TMPDIR/wpt.zip https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.zip
  unzip -q $TMPDIR/wpt.zip -d $TMPDIR
  mv "$TMPDIR/wordpress-develop-trunk/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
  mv "$TMPDIR/wordpress-develop-trunk/tests/phpunit/data" "$WP_TESTS_DIR/data"

  download "$WP_TESTS_DIR/wp-tests-config.php" https://raw.githubusercontent.com/wp-cli/wp-cli-tests/master/utils/wp-tests-config.php
  sed -i'' -e "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i'' -e "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i'' -e "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i'' -e "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i'' -e "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
}

install_db() {
  if [ "${SKIP_DB_CREATE}" = "true" ]; then return 0; fi
  PARTS=(${DB_HOST//\:/ }); HOST=${PARTS[0]}; PORT=${PARTS[1]}; EXTRA=""
  if [ -n "$PORT" ]; then
    if [[ "$PORT" =~ ^[0-9]+$ ]]; then EXTRA=" --host=$HOST --port=$PORT --protocol=tcp"; else EXTRA=" --socket=$PORT"; fi
  fi
  mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS"$EXTRA || true
}

install_wp
install_test_suite
install_db
