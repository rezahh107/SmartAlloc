<?php
// phpcs:ignoreFile

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\{Logging, ExportService};
use SmartAlloc\Tests\BaseTestCase;

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array { return ['basedir' => sys_get_temp_dir()]; }
}
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir): bool { return @mkdir($dir, 0777, true); }
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
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public int $insert_id = 1;
            public function __construct() {}
            public function prepare($q, ...$a): string { $args = is_array($a[0] ?? null) ? $a[0] : $a; return vsprintf($q, $args); }
        };
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
        $wpdbStub = $this->createStub(\wpdb::class);
        $wpdbStub->prefix = 'wp_';
        $tableResolverStub = new TableResolver($wpdbStub);

        $service = new ExportService($tableResolverStub, config: null, logger: new Logging());
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
