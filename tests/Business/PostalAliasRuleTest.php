<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Infra\GF\SabtEntryMapper;
use SmartAlloc\Services\ExportService;
use org\bovigo\vfs\vfsStream;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class PostalAliasRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Spreadsheet::class) || !class_exists(vfsStream::class)) {
            $this->markTestSkipped('PhpSpreadsheet/vfsStream unavailable');
        }
    }

    public function test_mapper_prefers_alias(): void
    {
        $mapper = new SabtEntryMapper();
        $res = $mapper->mapEntry([
            'postal_code' => '22222',
            'postal_code_alias' => '11111',
            '20' => '09123456789',
            '22' => '',
            '92' => 'M',
            '93' => 'G1',
            '94' => '1',
            '75' => '3',
            '39' => '',
        ]);
        $this->assertTrue($res['ok']);
        $this->assertSame('11111', $res['student']['postal_code']);
    }

    public function test_export_prefers_alias(): void
    {
        $row = ['postal_code' => '22222', 'postal_code_alias' => '11111'];
        $sheetConfig = [
            'columns' => [
                'pc' => ['source' => 'db', 'field_name' => 'postal_code', 'normalize' => ['digits_10']],
            ],
        ];
        $ref = new \ReflectionClass(ExportService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('normalizeSheetData');
        $method->setAccessible(true);
        $rows = $method->invoke($svc, $sheetConfig, [$row]);
        $this->assertSame('11111', $rows[0]['pc']);
    }
}
