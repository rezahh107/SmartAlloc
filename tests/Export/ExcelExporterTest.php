<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Export;

use PhpOffice\PhpSpreadsheet\IOFactory;
use SmartAlloc\Infra\Export\ExcelExporter;
use SmartAlloc\Infra\Export\CountersRepository;
use SmartAlloc\Tests\BaseTestCase;

final class ExcelExporterTest extends BaseTestCase
{
    private function sampleRows(): array
    {
        return [
            ['id' => 1, 'status' => 'allocated', 'reason_code' => '', 'fuzzy' => 0],
            ['id' => 2, 'status' => 'manual', 'reason_code' => 'MISSING_DOC', 'fuzzy' => 0],
            ['id' => 3, 'status' => 'rejected', 'reason_code' => 'INVALID_INFO', 'fuzzy' => 1],
        ];
    }

    private function createExporter(): ExcelExporter
    {
        $wpdb = $this->mockWpdb($this->sampleRows());
        $configPath = dirname(__DIR__, 2) . '/SmartAlloc_Exporter_Config_v1.json';
        $repo = new class extends CountersRepository {
            private int $d = 0;
            private int $b = 0;
            public function __construct() {}
            public function getNextCounters(): array { $this->d++; $this->b++; return [$this->d, $this->b]; }
        };
        return new ExcelExporter($wpdb, $configPath, sys_get_temp_dir(), $repo);
    }

    public function test_file_naming_pattern(): void
    {
        $exporter = $this->createExporter();
        $result   = $exporter->exportFromRows($this->sampleRows());
        $this->assertMatchesRegularExpression('/^SabtExport-ALLOCATED-\d{4}_\d{2}_\d{2}-\d{4}-B\d{3}\.xlsx$/', basename($result['path']));
        $this->assertFileExists($result['path']);
        unlink($result['path']);
    }

    public function test_summary_and_errors_sheets_exist(): void
    {
        $exporter = $this->createExporter();
        $result   = $exporter->exportFromRows($this->sampleRows());
        $spreadsheet = IOFactory::load($result['path']);
        $this->assertNotNull($spreadsheet->getSheetByName('Summary'));
        $this->assertNotNull($spreadsheet->getSheetByName('Errors'));
        unlink($result['path']);
    }

    public function test_column_layout_matches_config(): void
    {
        $exporter = $this->createExporter();
        $result   = $exporter->exportFromRows($this->sampleRows());
        $spreadsheet   = IOFactory::load($result['path']);
        $summarySheet  = $spreadsheet->getSheetByName('Summary');
        $errorsSheet   = $spreadsheet->getSheetByName('Errors');
        $config = json_decode(file_get_contents(dirname(__DIR__, 2) . '/SmartAlloc_Exporter_Config_v1.json'), true);

        $summaryHeaders = [];
        $summaryMax = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($summarySheet->getHighestColumn());
        for ($i = 1; $i <= $summaryMax; $i++) {
            $summaryHeaders[] = (string) $summarySheet->getCellByColumnAndRow($i, 1)->getValue();
        }
        $this->assertSame(array_column($config['Summary'], 'label'), $summaryHeaders);

        $errorHeaders = [];
        $errorMax = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($errorsSheet->getHighestColumn());
        for ($i = 1; $i <= $errorMax; $i++) {
            $errorHeaders[] = (string) $errorsSheet->getCellByColumnAndRow($i, 1)->getValue();
        }
        $this->assertSame(array_column($config['Errors'], 'label'), $errorHeaders);
        unlink($result['path']);
    }

    public function test_reject_and_manual_rows_appear_with_reason_codes(): void
    {
        $exporter = $this->createExporter();
        $result   = $exporter->exportFromRows($this->sampleRows());
        $spreadsheet = IOFactory::load($result['path']);
        $errorsSheet = $spreadsheet->getSheetByName('Errors');
        $rows = $errorsSheet->toArray();
        $this->assertCount(3, $rows); // header + 2 rows
        $this->assertSame('manual', strtolower((string) $rows[1][1]));
        $this->assertSame('MISSING_DOC', $rows[1][2]);
        $this->assertSame('rejected', strtolower((string) $rows[2][1]));
        $this->assertSame('INVALID_INFO', $rows[2][2]);
        unlink($result['path']);
    }

    private function mockWpdb(array $results)
    {
        return new class($results) {
            public $prefix = 'wp_';
            public function __construct(private array $results) {}
            public function prepare($query, ...$args) { return vsprintf($query, $args); }
            public function get_results($sql, $mode) { return $this->results; }
        };
    }
}

