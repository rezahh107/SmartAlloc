<?php
// phpcs:ignoreFile
/**
 * WordPress tests configuration (docker-compatible)
 */

define( 'DB_NAME', getenv('WP_TESTS_DB_NAME') ?: 'wordpress_tests' );
define( 'DB_USER', getenv('WP_TESTS_DB_USER') ?: 'wp' );
define( 'DB_PASSWORD', getenv('WP_TESTS_DB_PASS') ?: 'wp' );
define( 'DB_HOST', getenv('WP_TESTS_DB_HOST') ?: 'db' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'ABSPATH', __DIR__ . '/wordpress/' );

define( 'WP_DEBUG', true );
define( 'SCRIPT_DEBUG', true );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

define( 'WP_TESTS_DB_CREATE', false );
define( 'WP_TESTS_DIR', '/tmp/wordpress-tests-lib' );

$table_prefix = 'wptests_';

