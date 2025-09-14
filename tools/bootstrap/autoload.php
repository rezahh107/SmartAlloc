<?php

// Prevent wp-phpunit from bootstrapping WordPress during unit tests
if (getenv('WP_INTEGRATION') !== '1') {
    putenv('WP_PHPUNIT__TESTS_CONFIG=/dev/null');
    if (!defined('WP_PHPUNIT__TESTS_CONFIG')) {
        define('WP_PHPUNIT__TESTS_CONFIG', '/dev/null');
    }
}

// Fail-fast Composer autoload
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException("Missing Composer autoload at $autoload");
}
require $autoload;

// Timezone & test-time helpers
require __DIR__ . '/time.php';

// Per-test Brain Monkey hooks via PHPUnit extension
require __DIR__ . '/mocks.php';

// Default WordPress function stubs for unit tests
require __DIR__ . '/stubs.php';

// Only for Integration (explicit opt-in)
require __DIR__ . '/environment.php';
