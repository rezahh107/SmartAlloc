<?php
// phpcs:ignoreFile

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\{Logging, ExportService};
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Tests\Helpers\TestWpdb;

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array { return ['basedir' => sys_get_temp_dir()]; }
}
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir): bool { return @mkdir($dir, 0777, true); }
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

final class ExportSmokeTest extends BaseTestCase
{
    private $oldWpdb;
    private $oldUploadDir;

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->oldWpdb = $wpdb;
        $wpdb = new TestWpdb();
        $this->oldUploadDir = $GLOBALS['wp_upload_dir_basedir'] ?? null;
        $GLOBALS['wp_upload_dir_basedir'] = sys_get_temp_dir();
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->oldWpdb;
        if ($this->oldUploadDir !== null) {
            $GLOBALS['wp_upload_dir_basedir'] = $this->oldUploadDir;
        } else {
            unset($GLOBALS['wp_upload_dir_basedir']);
        }
        parent::tearDown();
    }
    public function test_export_creates_file_and_sheets(): void
    {
        $tableResolver = new TableResolver($GLOBALS['wpdb']);

        $service = new ExportService($tableResolver, config: null, logger: new Logging());
        $res = $service->export(new FormContext(1));
        $file = $res['file'];
        $this->assertMatchesRegularExpression('/SabtExport-ALLOCATED-\d{4}_\d{2}_\d{2}-\d{4}-B\d{3}\.xlsx$/', basename($file));
        $spreadsheet = IOFactory::load($file);
        $this->assertNotNull($spreadsheet->getSheetByName('Sheet2'));
        $this->assertNotNull($spreadsheet->getSheetByName('Sheet5'));
        $this->assertNotNull($spreadsheet->getSheetByName('9394'));
        @unlink($file);
    }
}
