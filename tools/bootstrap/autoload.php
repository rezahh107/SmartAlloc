<?php
// phpcs:ignoreFile

// Prevent wp-phpunit from bootstrapping WordPress during unit tests
if (getenv('WP_INTEGRATION') !== '1') {
    putenv('WP_PHPUNIT__TESTS_CONFIG=/dev/null');
    if (!defined('WP_PHPUNIT__TESTS_CONFIG')) {
        define('WP_PHPUNIT__TESTS_CONFIG', '/dev/null');
    }
}

// Fail-fast Composer autoload
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "[BOOTSTRAP] Composer autoload not found\n");
    exit(1);
}
require_once $autoload;

// Enforce UTC determinism
ini_set('date.timezone', 'UTC');
date_default_timezone_set('UTC');

// UNIT sentinel: WordPress/DB must not load when WP_INTEGRATION != 1
register_shutdown_function(function () {
    $isIntegration = getenv('WP_INTEGRATION') === '1';
    if (!$isIntegration) {
        if (defined('ABSPATH')) {
            fwrite(STDERR, "[UNIT_VIOLATION] ABSPATH defined in unit mode\n");
            exit(1);
        }
        if (isset($GLOBALS['wpdb']) || class_exists('wpdb', false)) {
            fwrite(STDERR, "[UNIT_VIOLATION] \$wpdb touched in unit mode\n");
            exit(1);
        }
    }
});

// Per-test Brain Monkey hooks via PHPUnit extension
require __DIR__ . '/mocks.php';

// Default WordPress function stubs for unit tests
require __DIR__ . '/stubs.php';

// Only for Integration (explicit opt-in)
require __DIR__ . '/environment.php';
