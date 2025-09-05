<?php
/**
 * WP test configuration for SmartAlloc.
 *
 * @package SmartAlloc
 */

define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '127.0.0.1' );

define( 'ABSPATH', __DIR__ . '/wordpress/' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

$table_prefix = 'wp_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
