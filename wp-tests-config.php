<?php
/**
 * WP test configuration for SmartAlloc.
 */

$env = [];
$env_file = __DIR__ . '/.env';
if ( file_exists( $env_file ) ) {
    $env = parse_ini_file( $env_file, false, INI_SCANNER_RAW ) ?: [];
}

$env_or = static function (string $key, $default = null) use ($env) {
    if (getenv($key) !== false && getenv($key) !== '') {
        return getenv($key);
    }
    return $env[$key] ?? $default;
};

define( 'DB_NAME', $env_or('WP_TEST_DB_NAME', $env_or('WORDPRESS_DB_NAME', 'wordpress_tests')) );
define( 'DB_USER', $env_or('WP_TEST_DB_USER', $env_or('WORDPRESS_DB_USER', 'root')) );
define( 'DB_PASSWORD', $env_or('WP_TEST_DB_PASSWORD', $env_or('WORDPRESS_DB_PASSWORD', 'root')) );
define( 'DB_HOST', $env_or('WP_TEST_DB_HOST', $env_or('WORDPRESS_DB_HOST', '127.0.0.1')) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', 'utf8mb4_unicode_ci' );

$core_dir = $env_or('WP_CORE_DIR', null);
if (!$core_dir) {
    // Prefer WordPress installed in container if present
    if (is_dir('/var/www/html') && file_exists('/var/www/html/wp-settings.php')) {
        $core_dir = '/var/www/html';
    } else {
        $core_dir = __DIR__ . '/wordpress';
    }
}
define( 'ABSPATH', rtrim($core_dir, '/\\') . '/' );

define( 'WP_DEBUG', true );
define( 'SCRIPT_DEBUG', true );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

$table_prefix = 'wp_';
