<?php
require_once __DIR__ . '/../vendor/autoload.php';

$wpTestsDir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';
if (file_exists($wpTestsDir . '/includes/functions.php')) {
    require_once $wpTestsDir . '/includes/functions.php';
    require_once $wpTestsDir . '/includes/bootstrap.php';
}
