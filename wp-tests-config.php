<?php
/**
 * WP test configuration for SmartAlloc.
 */

$env = [];
$env_file = __DIR__ . '/.env';
if ( file_exists( $env_file ) ) {
    $env = parse_ini_file( $env_file, false, INI_SCANNER_RAW ) ?: [];
}

define( 'DB_NAME', $env['WP_TEST_DB_NAME'] ?? 'wordpress_tests' );
define( 'DB_USER', $env['DB_USER'] ?? 'root' );
define( 'DB_PASSWORD', $env['DB_PASSWORD'] ?? 'root' );
define( 'DB_HOST', '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', 'utf8mb4_unicode_ci' );

define( 'ABSPATH', __DIR__ . '/wordpress/' );

define( 'WP_DEBUG', true );
define( 'SCRIPT_DEBUG', true );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

$table_prefix = 'wp_';
