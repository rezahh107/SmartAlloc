#!/bin/bash
WP_CORE_DIR="/tmp/wordpress"
WP_TESTS_DIR="/tmp/wordpress-tests-lib"
mkdir -p $WP_CORE_DIR
mkdir -p $WP_TESTS_DIR
curl -s https://raw.githubusercontent.com/WordPress/wordpress-develop/master/tests/phpunit/includes/install.php -o $WP_TESTS_DIR/install.php
curl -s https://raw.githubusercontent.com/WordPress/wordpress-develop/master/wp-tests-config-sample.php -o $WP_TESTS_DIR/wp-tests-config.php
sed -i "s|dirname( __FILE__ )|'/tmp/wordpress-tests-lib'|" $WP_TESTS_DIR/wp-tests-config.php

