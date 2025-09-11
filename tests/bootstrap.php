<?php
// phpcs:ignoreFile

// Load Patchwork before anything else
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Define required constants
if (!defined('SMARTALLOC_CAP')) {
    define('SMARTALLOC_CAP', 'manage_smartalloc');
}

// Initialize Brain Monkey
\Brain\Monkey\setUp();

// Optional WordPress function stubs
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return $text;
    }
}

// Stub WP_UnitTestCase for integration tests
if (!class_exists('WP_UnitTestCase')) {
    class WP_UnitTestCase extends PHPUnit\Framework\TestCase
    {
    }
}
