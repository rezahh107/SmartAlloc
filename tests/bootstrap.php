<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('WP_TESTS_DOMAIN')) {
    define('WP_TESTS_DOMAIN', 'example.org');
}
if (!defined('WP_TESTS_EMAIL')) {
    define('WP_TESTS_EMAIL', 'admin@example.org');
}
if (!defined('WP_TESTS_TITLE')) {
    define('WP_TESTS_TITLE', 'Test Blog');
}
if (!defined('WP_PHP_BINARY')) {
    define('WP_PHP_BINARY', 'php');
}

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';
}

require $_tests_dir . '/includes/functions.php';

function _manually_load_plugin(): void {
    require dirname(__DIR__) . '/smart-alloc.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';
