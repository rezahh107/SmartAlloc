<?php
// phpcs:ignoreFile
declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Http\Ajax\ExportStreamAction;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\{ExportService, Logging};
use SmartAlloc\Tests\BaseTestCase;

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
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

class TestWpdb extends wpdb {
    public string $prefix = 'wp_';
    public string $last_query = '';
    private int $calls = 0;
    public function __construct() {}
    public function query($sql): void { $this->last_query = $sql; }
    public function get_results($sql, $output = OBJECT): array
    {
        $this->last_query = $sql;
        if ($this->calls++ > 0) {
            return [];
        }
        return [(object) ['id' => 1, 'national_id' => '1', 'mobile' => '2', 'postal' => '3']];
    }
    public function prepare($q, ...$a): string { $args = is_array($a[0] ?? null) ? $a[0] : $a; return vsprintf($q, $args); }
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
        $GLOBALS['wpdb'] = new TestWpdb();
        Functions\when('add_action');
        Functions\expect('nocache_headers');
        Functions\when('filter_input')->alias(fn($type, $var) => $_GET[$var] ?? null);
        $_GET['_wpnonce'] = 'valid_nonce_smartalloc_export_stream';
        $_GET['limit']    = '1';
        $wpdbStub = $this->createStub(\wpdb::class);
        $wpdbStub->prefix = 'wp_';
        $tableResolverStub = new TableResolver($wpdbStub);
        $svc    = new ExportService($tableResolverStub, config: null, logger: new Logging());
        $action = new ExportStreamAction($svc);
        ob_start();
        $action->handle();
        $out = ob_get_clean();
        $this->assertStringContainsString('PK', (string) $out);
    }

    public function test_error_handling_persists_to_database(): void
    {
        $GLOBALS['wpdb'] = new TestWpdb();
        $wpdbStub = $this->createStub(\wpdb::class);
        $wpdbStub->prefix = 'wp_';
        $tableResolverStub = new TableResolver($wpdbStub);
        $svc = new ExportService($tableResolverStub, config: null, logger: new Logging());
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
