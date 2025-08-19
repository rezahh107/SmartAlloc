<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ExporterImporter;

use SmartAlloc\Tests\BaseTestCase;
use org\bovigo\vfs\vfsStream;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use SmartAlloc\Services\ExportService;
use ReflectionClass;

final class LegacySheetTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Spreadsheet::class) || !class_exists(vfsStream::class)) {
            $this->markTestSkipped('PhpSpreadsheet/vfsStream unavailable');
        }
    }

    public function test_legacy_sheet_population(): void
    {
        $rows = [
            ['1' => 'A', 'legacy' => true],
            ['1' => 'B', 'legacy' => false],
        ];

        $sheetConfig = [
            'columns' => [
                'id' => ['source' => 'gf', 'field_id' => '1'],
            ],
        ];

        $ref = new ReflectionClass(ExportService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $m = $ref->getMethod('normalizeSheetData');
        $m->setAccessible(true);
        $normalized = $m->invoke($svc, $sheetConfig, $rows);

        $legacyRows = array_values(array_filter($rows, static fn($r) => !empty($r['legacy'])));
        $this->assertCount(1, $legacyRows);
        $this->assertSame('A', $normalized[0]['id']);
    }
}
