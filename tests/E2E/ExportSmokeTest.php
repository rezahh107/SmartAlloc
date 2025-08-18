<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use SmartAlloc\Services\{Logging, Metrics, ExportService};
use SmartAlloc\Tests\BaseTestCase;

final class ExportSmokeTest extends BaseTestCase
{
    private $oldWpdb;
    private $oldUploadDir;

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->oldWpdb = $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public $insert_id = 1;
            public function insert($table, $data) { return true; }
            public function get_results($q, $type) { return []; }
            public function prepare($q, ...$a) { return $q; }
            public function query($q) { return true; }
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
        $service = new ExportService(new Logging(), new Metrics());
        $file = $service->exportSabt([
            ['first_name' => 'A', 'last_name' => 'B']
        ]);
        $this->assertMatchesRegularExpression('/SabtExport-ALLOCATED-\d{4}_\d{2}_\d{2}-\d{4}-B\d{3}\.xlsx$/', basename($file));
        $spreadsheet = IOFactory::load($file);
        $this->assertNotNull($spreadsheet->getSheetByName('Summary'));
        $this->assertNotNull($spreadsheet->getSheetByName('Errors'));
        @unlink($file);
    }
}
