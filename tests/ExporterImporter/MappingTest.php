<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ExporterImporter;

use SmartAlloc\Tests\BaseTestCase;
use org\bovigo\vfs\vfsStream;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SmartAlloc\Services\ExportService;
use ReflectionClass;

final class MappingTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Spreadsheet::class) || !class_exists(vfsStream::class)) {
            $this->markTestSkipped('PhpSpreadsheet/vfsStream unavailable');
        }
    }

    public function test_gf_to_excel_mapping(): void
    {
        $entry = [
            '1'  => 'علی',
            '2'  => 'رضایی',
            '92' => 'F',
            '93' => 'G1',
        ];

        $sheetConfig = [
            'columns' => [
                'نام'         => ['source' => 'gf', 'field_id' => '1'],
                'نام خانوادگی' => ['source' => 'gf', 'field_id' => '2'],
                'جنسیت'       => [
                    'source'   => 'gf',
                    'field_id' => '92',
                    'choices'  => ['M' => 'پسر', 'F' => 'دختر'],
                ],
                'گروه'       => ['source' => 'gf', 'field_id' => '93'],
            ],
        ];

        $ref = new ReflectionClass(ExportService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $m   = $ref->getMethod('normalizeSheetData');
        $m->setAccessible(true);
        $rows = $m->invoke($svc, $sheetConfig, [$entry]);

        $data = $rows[0];
        $choices = $sheetConfig['columns']['جنسیت']['choices'];
        if (isset($choices[$data['جنسیت']])) {
            $data['جنسیت'] = $choices[$data['جنسیت']];
        }

        $root = vfsStream::setup('root');
        $file = vfsStream::url('root/out.xlsx');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(array_values($data), null, 'A1');
        $writer = new Xlsx($spreadsheet);
        $writer->save($file); // ensure vfsStream handles Excel writes

        $this->assertSame('علی', $sheet->getCell('A1')->getValue());
        $this->assertSame('رضایی', $sheet->getCell('B1')->getValue());
        $this->assertSame('دختر', $sheet->getCell('C1')->getValue());
        $this->assertSame('G1', $sheet->getCell('D1')->getValue());
    }
}
