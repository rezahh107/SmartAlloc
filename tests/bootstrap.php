<?php
/** Test bootstrap */
declare(strict_types=1);

date_default_timezone_set('UTC');

// Load Composer autoloader if available.
$__autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($__autoload)) {
    require $__autoload;
}

// Prefer environment override for WP_PHPUNIT__DIR, fallback to vendor path.
if (!defined('WP_PHPUNIT__DIR')) {
    $wpPhpUnitDir = getenv('WP_PHPUNIT__DIR') ?: dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';
    define('WP_PHPUNIT__DIR', $wpPhpUnitDir);
}

// Point PHPUnit to the test configuration file used by wp-phpunit setup.
if (!defined('WP_TESTS_CONFIG_FILE_PATH')) {
    define('WP_TESTS_CONFIG_FILE_PATH', dirname(__DIR__) . '/wp-tests-config.php');
}

// Bootstrap WordPress test environment.
require WP_PHPUNIT__DIR . '/includes/bootstrap.php';

// Load integration support stubs if present.
$__wpdb_stub = __DIR__ . '/Integration/Support/TestWpdb.php';
if (file_exists($__wpdb_stub)) {
    require_once $__wpdb_stub;
}
