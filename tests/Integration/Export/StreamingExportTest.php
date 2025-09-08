<?php
// phpcs:ignoreFile
declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Http\Ajax\ExportStreamAction;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\{ExportService, Logging};
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Tests\Helpers\TestWpdb;

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array { return ['basedir' => sys_get_temp_dir()]; }
}
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir): bool { return @mkdir($dir, 0777, true); }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap): bool { return true; }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) { return $value; }
}
if (!function_exists('esc_html')) {
    function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('wp_die')) {
    function wp_die($m = '') { echo $m; }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($d = null, $s = null) { return ['success' => false, 'data' => $d]; }
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
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
        $db = new TestWpdb();
        $db->results = [(object) ['id' => 1, 'national_id' => '1', 'mobile' => '2', 'postal' => '3']];
        $GLOBALS['wpdb'] = $db;
        Functions\when('add_action');
        Functions\expect('nocache_headers');
        Functions\when('filter_input')->alias(fn($type, $var) => $_GET[$var] ?? null);
        $_GET['_wpnonce'] = 'valid_nonce_smartalloc_export_stream';
        $_GET['limit']    = '1';
        $tableResolver = new TableResolver($db);
        $svc    = new ExportService($tableResolver, config: null, logger: new Logging());
        $action = new ExportStreamAction($svc);
        ob_start();
        $action->handle();
        $out = ob_get_clean();
        $this->assertStringContainsString('PK', (string) $out);
    }

    public function test_error_handling_persists_to_database(): void
    {
        $GLOBALS['wpdb'] = new TestWpdb();
        $tableResolver = new TableResolver($GLOBALS['wpdb']);
        $svc = new ExportService($tableResolver, config: null, logger: new Logging());
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
