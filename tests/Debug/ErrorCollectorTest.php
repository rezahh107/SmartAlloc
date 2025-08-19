<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Debug;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Debug\ErrorCollector;
use SmartAlloc\Debug\ErrorStore;
use SmartAlloc\Debug\RedactionAdapter;
use SmartAlloc\Infra\Logging\Logger;
use SmartAlloc\Tests\BaseTestCase;

final class ErrorCollectorTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        if (!defined('WP_PLUGIN_DIR')) {
            define('WP_PLUGIN_DIR', '/plugin');
        }
        $GLOBALS['sa_options'] = [];
        Functions\when('get_bloginfo')->alias(fn() => '6.0');
        Functions\when('wp_parse_url')->alias(fn($v) => parse_url($v));
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('wp_die')->alias(fn($m) => throw new \RuntimeException($m));
        Functions\when('get_current_user_id')->alias(fn() => 1);
        $GLOBALS['_SERVER']['REQUEST_URI'] = '/wp-json/test';
        $GLOBALS['_SERVER']['REQUEST_METHOD'] = 'POST';
        $GLOBALS['sa_options']['smartalloc_debug_enabled'] = true;
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $GLOBALS['sa_options'] = [];
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    public function test_stores_redacted_entry_and_clamp(): void
    {
        $logger = new Logger();
        $logger->info('note', ['mobile' => '1234567', 'email' => 'a@b.com']);
        $collector = new ErrorCollector(new RedactionAdapter(), $logger);
        $collector->register();
        $collector->handleException(new \Exception('Boom'));

        $entries = $GLOBALS['sa_options']['smartalloc_debug_errors'] ?? [];
        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertSame('Boom', $entry['message'] ?? null);
        $this->assertStringNotContainsString('a@b.com', json_encode($entry));

        ErrorStore::add(['message' => str_repeat('x', 200000)]);
        $entries = $GLOBALS['sa_options']['smartalloc_debug_errors'] ?? [];
        $this->assertSame('Entry exceeded size limit', $entries[0]['message']);
    }
}
