<?php // phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Http\Ajax {
    function filter_input($type, $var_name, $filter = FILTER_DEFAULT, $options = []) {
        return $type === INPUT_GET ? ($_GET[$var_name] ?? null) : null;
    }
}

namespace {
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

use SmartAlloc\Http\Ajax\ExportStreamAction;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\{ExportService, Logging};
use SmartAlloc\Tests\BaseTestCase;

// Use the shared TestWpdb stub to avoid typed property conflicts.
require_once dirname(__DIR__) . '/Support/TestWpdb.php';
class TestWpdbWithData extends \TestWpdb {
    private int $calls = 0;
    public function get_results($sql = null, $output = OBJECT) {
        $this->last_query = (string) $sql;
        if ($this->calls++ > 0) { return []; }
        return [(object) ['id' => 1, 'national_id' => '1', 'mobile' => '2', 'postal' => '3']];
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array { return ['basedir' => sys_get_temp_dir()]; }
}
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir): bool { return is_dir($dir) || mkdir($dir, 0777, true); }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap): bool { return true; }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status = null): void { echo json_encode(['success' => false, 'data' => $data]); }
}
if (!function_exists('nocache_headers')) {
    function nocache_headers(): void {}
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($v) { return $v; }
}

final class StreamingExportTest extends BaseTestCase
{
    public function test_ajax_endpoint_streams_correctly(): void
    {
        $GLOBALS['wpdb'] = new TestWpdbWithData();
        $_GET['_wpnonce'] = wp_create_nonce('smartalloc_export_stream');
        $_GET['limit']    = '1';
        $tables = new TableResolver($GLOBALS['wpdb']);
        $svc    = new ExportService($tables, config: null, logger: new Logging());
        $action = new ExportStreamAction($svc);
        ob_start();
        $action->handle();
        $out = ob_get_clean();
        $this->assertStringContainsString('PK', (string) $out);
    }

    public function test_error_handling_persists_to_database(): void
    {
        $GLOBALS['wpdb'] = new TestWpdbWithData();
        $tables = new TableResolver($GLOBALS['wpdb']);
        $svc    = new ExportService($tables, config: null, logger: new Logging());
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
}
