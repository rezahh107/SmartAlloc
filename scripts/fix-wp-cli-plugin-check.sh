#!/bin/bash
set -e
command -v wp >/dev/null || {
  curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar;
  chmod +x wp-cli.phar;
  mv wp-cli.phar /usr/local/bin/wp || mv wp-cli.phar ./wp;
}
mkdir -p /tmp/wp-test && cd /tmp/wp-test
wp core download --allow-root --quiet
wp config create --dbname=test --dbuser=test --dbpass=test --allow-root --skip-check
cd - >/dev/null
WP_CLI_PACKAGES_DIR=/tmp/wp-test wp plugin check ./ --allow-root --format=json || {
  wp package install wp-cli/plugin-check-command --allow-root;
  wp plugin check ./ --allow-root --format=json;
}
