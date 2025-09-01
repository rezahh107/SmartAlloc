<?php
declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Http\Ajax\ExportStreamAction;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\ExportService;
use SmartAlloc\Tests\BaseTestCase;

if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public string $last_query = '';
        public function query($sql): void { $this->last_query = $sql; }
    }
}

final class StreamingExportTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_ajax_endpoint_streams_correctly(): void
    {
        $GLOBALS['wpdb'] = new wpdb();
        Functions\when('add_action');
        Functions\expect('wp_verify_nonce')->with('n', 'smartalloc_export_stream')->andReturn(true);
        Functions\expect('current_user_can')->with('smartalloc_manage')->andReturn(true);
        Functions\expect('wp_send_json_error')->never();
        Functions\expect('nocache_headers');
        $_GET['_wpnonce'] = 'n';
        $_GET['limit']    = '1';
        $svc    = new ExportService(new TableResolver($GLOBALS['wpdb']));
        $action = new ExportStreamAction($svc);
        ob_start();
        $action->handle();
        $out = ob_get_clean();
        $this->assertStringContainsString('PK', (string) $out);
    }

    public function test_error_handling_persists_to_database(): void
    {
        $GLOBALS['wpdb'] = new wpdb();
        $svc = new ExportService(new TableResolver($GLOBALS['wpdb']));
        $ref = new \ReflectionClass($svc);
        $m   = $ref->getMethod('bulkInsertErrors');
        $m->setAccessible(true);
        $m->invoke($svc, [
            ['allocation_id' => 5, 'error_type' => 'E', 'error_message' => 'oops'],
            ['allocation_id' => 6, 'error_type' => 'E', 'error_message' => 'bad'],
        ]);
        $this->assertStringContainsString('INSERT INTO', $GLOBALS['wpdb']->last_query);
    }
}
