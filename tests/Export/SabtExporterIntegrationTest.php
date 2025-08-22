<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Export;

use PhpOffice\PhpSpreadsheet\IOFactory;
use SmartAlloc\Infra\Export\SabtExporter;
use SmartAlloc\Tests\BaseTestCase;

final class SabtExporterIntegrationTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(IOFactory::class)) {
            $this->markTestSkipped('PhpSpreadsheet unavailable');
        }
    }

    public function test_export_creates_summary_and_errors(): void
    {
        $entries = [
            ['20' => '09123456789', '76' => '1234567890123456', 'postal_code' => '1234567890', '75' => '3', 'hakmat_code' => 'H1', 'hakmat_name' => 'Hakmat'],
            ['20' => '123', '76' => '1234567890123456', 'postal_code' => '1234567890', '75' => '3', 'hakmat_code' => 'H2', 'hakmat_name' => 'Hakmat2'],
            ['20' => '09123456789', '76' => '1234567890123456', 'postal_code' => '1234567890', '75' => '2', 'hakmat_code' => 'HX', 'hakmat_name' => 'ShouldClear'],
        ];

        $exporter = new SabtExporter(dirname(__DIR__, 2) . '/config/SmartAlloc_Exporter_Config_v1.json');
        $result = $exporter->exportFromEntries($entries);

        $spreadsheet = IOFactory::load($result['path']);
        $summary = $spreadsheet->getSheetByName('Summary')->toArray(null, true, true, true);
        $errors = $spreadsheet->getSheetByName('Errors')->toArray(null, true, true, true);

        $this->assertCount(3, $summary); // header + 2 valid rows
        $this->assertSame('H1', $summary[2]['D']);
        $this->assertNull($summary[3]['E']);

        $this->assertCount(2, $errors); // header + 1 error
        $this->assertSame('invalid_mobile', $errors[2]['B']);

        unlink($result['path']);
        $reportPath = dirname(__DIR__, 2) . '/artifacts/export/export-report.json';
        $report = json_decode(file_get_contents($reportPath), true);
        $this->assertSame(['total' => 3, 'valid' => 2, 'errors' => 1], $report);
        @unlink($reportPath);
    }
}
