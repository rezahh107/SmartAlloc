<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Tests\Security;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Debug\ErrorCollector;
use SmartAlloc\Debug\PromptBuilder;
use SmartAlloc\Infra\Logging\Logger;
use SmartAlloc\Tests\BaseTestCase;

final class DebugKitTest extends BaseTestCase
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
        $GLOBALS['_SERVER']['REQUEST_URI'] = '/api';
        $GLOBALS['_SERVER']['REQUEST_METHOD'] = 'POST';
        $GLOBALS['sa_options']['smartalloc_debug_enabled'] = true;
        global $wpdb;
        $wpdb = (object) ['queries' => [
            ['SELECT * FROM t WHERE id = 5', 0, 'wpdb->prepare'],
            ['SELECT * FROM bad', 0, '']
        ]];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $GLOBALS['sa_options'] = [];
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    public function test_no_pii_and_only_prepared_queries(): void
    {
        $logger = new Logger();
        $logger->info('ctx', ['email' => 'user@example.com', 'mobile' => '123456789']);
        $collector = new ErrorCollector(null, $logger);
        $collector->register();
        $tmp = tempnam(sys_get_temp_dir(), 'sa');
        file_put_contents($tmp, '<?php\n');
        $collector->handleError(E_USER_ERROR, 'fail', $tmp, 1);

        $entry = ($GLOBALS['sa_options']['smartalloc_debug_errors'] ?? [])[0] ?? [];
        $prompt = (new PromptBuilder())->build($entry);
        $this->assertStringNotContainsString('user@example.com', $prompt);
        $this->assertStringNotContainsString('123456789', $prompt);
        $queries = $entry['queries'] ?? [];
        $this->assertSame(['SELECT * FROM t WHERE id = ?'], $queries);
        $this->assertNotContains('SELECT * FROM bad', $queries);
        unlink($tmp);
    }
}
