<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Debug\ErrorCollector;
use SmartAlloc\Debug\PromptBuilder;
use SmartAlloc\Infra\Logging\Logger;
use SmartAlloc\Tests\BaseTestCase;

final class DebugIntegrationTest extends BaseTestCase
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
        if (!defined('SAVEQUERIES')) {
            define('SAVEQUERIES', true);
        }
        $GLOBALS['sa_options'] = ['smartalloc_debug_errors' => []];
        Functions\when('get_bloginfo')->alias(fn() => '6.0');
        Functions\when('wp_parse_url')->alias(fn($v) => parse_url($v));
        Functions\when('get_current_user_id')->alias(fn() => 1);
        $GLOBALS['_SERVER']['REQUEST_URI'] = '/wp-json/foo';
        $GLOBALS['_SERVER']['REQUEST_METHOD'] = 'GET';
        $GLOBALS['sa_options']['smartalloc_debug_enabled'] = true;
        global $wpdb;
        $wpdb = (object) ['queries' => [['SELECT * FROM t WHERE id = %d', 1]]];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $GLOBALS['sa_options'] = [];
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    public function test_prompt_contains_route_and_throttle(): void
    {
        $logger = new Logger();
        $collector = new ErrorCollector(null, $logger);
        $collector->register();
        $collector->handleError(E_USER_ERROR, 'oops', __FILE__, __LINE__);
        $collector->handleError(E_USER_ERROR, 'oops', __FILE__, __LINE__);

        $entries = $GLOBALS['sa_options']['smartalloc_debug_errors'] ?? [];
        $this->assertNotEmpty($entries);
        $prompt = (new PromptBuilder())->build($entries[0]);
        $this->assertStringContainsString('/wp-json/foo', $prompt);
        $this->assertStringContainsString('correlation_id', $prompt);
    }
}
