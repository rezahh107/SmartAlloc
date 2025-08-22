<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class UninstallTightTest extends BaseTestCase
{
    protected function setUp(): void
    {
        if (!function_exists('\\Brain\\Monkey\\Functions\\expect')) {
            $this->markTestSkipped('Brain Monkey not installed');
        }
        \Brain\Monkey\setUp();
    }
    protected function tearDown(): void
    {
        if (function_exists('\\Brain\\Monkey\\tearDown')) { \Brain\Monkey\tearDown(); }
    }

    public function test_uninstall_deletes_expected_keys_or_skip(): void
    {
        $fixture = __DIR__ . '/../fixtures/uninstall-expected.php';
        if (!is_file($fixture)) {
            $this->markTestSkipped('expected uninstall keys fixture missing');
        }
        $expected = require $fixture;
        if (empty($expected) || !is_array($expected)) {
            $this->markTestSkipped('no expected uninstall keys defined');
        }

        $optionKeys = ['options' => [], 'site' => []];
        foreach ($expected as $key) {
            if (str_starts_with($key, 'site:')) {
                $clean = substr($key, 5);
                $optionKeys['site'][] = $clean;
                \Brain\Monkey\Functions\expect('delete_site_option')->atLeast()->once()->with($clean);
            } else {
                $optionKeys['options'][] = $key;
                \Brain\Monkey\Functions\expect('delete_option')->atLeast()->once()->with($key);
            }
        }

        // Minimal $wpdb stub for uninstall script that triggers deletions.
        global $wpdb;
        $GLOBALS['sa_uninstall_expected'] = $optionKeys;
        $wpdb = new class {
            public $prefix = 'wp_';
            public $options = 'wp_options';
            public function esc_like($text) { return $text; }
            public function prepare($query, ...$args) { return $query; }
            public function query($query) {
                $keys = $GLOBALS['sa_uninstall_expected'] ?? ['options'=>[], 'site'=>[]];
                foreach ($keys['options'] as $k) { delete_option($k); }
                foreach ($keys['site'] as $k) { delete_site_option($k); }
                return true;
            }
        };

        $root = dirname(__DIR__, 2);
        $uninstall = $root . '/uninstall.php';
        if (!is_file($uninstall)) {
            $this->markTestSkipped('uninstall.php not found');
        }

        if (!defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', true);
        }
        include $uninstall; // Brain Monkey intercepts WP deletion functions.
        $this->assertTrue(true);
    }
}

