<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../stubs/wp-stubs.php';
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/..');
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}
