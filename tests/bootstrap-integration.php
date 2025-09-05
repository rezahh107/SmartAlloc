<?php
// phpcs:ignoreFile
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';
$core_dir  = getenv('WP_CORE_DIR') ?: '/tmp/wordpress';

if (file_exists("$tests_dir/bootstrap.php")) {
    require_once "$tests_dir/bootstrap.php";
} elseif (file_exists("$core_dir/wp-settings.php")) {
    define('ABSPATH', $core_dir . '/');
    require_once ABSPATH . 'wp-settings.php';
}
