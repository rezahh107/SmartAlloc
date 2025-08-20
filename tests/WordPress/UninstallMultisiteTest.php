<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use Brain\Monkey;
use Brain\Monkey\Functions;

if (class_exists('WP_UnitTestCase')) {
    abstract class WpBaseTestCase extends WP_UnitTestCase {}
} else {
    abstract class WpBaseTestCase extends TestCase {
        protected function setUp(): void
        {
            $this->markTestSkipped('WP_UnitTestCase not available');
        }
    }
}

final class UninstallMultisiteTest extends WpBaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('\\Brain\\Monkey\\setUp')) {
            $this->markTestSkipped('Brain Monkey not installed');
        }
        if (!class_exists(vfsStream::class)) {
            $this->markTestSkipped('vfsStream not installed');
        }
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_network_activation_deactivation_smoke(): void
    {
        if (!function_exists('is_multisite') || !is_multisite()) {
            $this->markTestSkipped('Requires multisite');
        }

        $pluginFile = dirname(__DIR__, 2) . '/smart-alloc.php';
        $plugin = plugin_basename($pluginFile);

        activate_plugin($plugin, '', true, true);
        deactivate_plugins($plugin, false, true);

        $this->assertTrue(true);
    }

    public function test_uninstall_cleans_options_and_transients(): void
    {
        $wpdb = new class {
            public $options = 'wp_options';
            public array $queries = [];
            public function esc_like($s) { return $s; }
            public function prepare($q, ...$args) { $this->queries[] = ['prepare', $q, $args]; return $q; }
            public function query($q) { $this->queries[] = ['query', $q]; return true; }
        };
        $GLOBALS['wpdb'] = $wpdb;

        Functions\expect('get_option')
            ->once()
            ->with('smartalloc_purge_on_uninstall', false)
            ->andReturn(true);

        $root = vfsStream::setup('plugin');
        $contents = file_get_contents(dirname(__DIR__, 2) . '/uninstall.php');
        vfsStream::newFile('uninstall.php')->at($root)->setContent($contents);

        if (!defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', true);
        }

        include vfsStream::url('plugin/uninstall.php');

        $queries = array_filter($wpdb->queries, fn($q) => $q[0] === 'query');
        $this->assertCount(2, $queries);
    }
}
