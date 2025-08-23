<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\ExportService;
use SmartAlloc\Tests\BaseTestCase;

final class ExportServiceTest extends BaseTestCase
{
    /** @test */
    public function writes_three_sheets_with_string_codes(): void
    {
        global $wpdb;
        $svc = new ExportService(new TableResolver($wpdb));
        $res = $svc->export(new FormContext(1));
        $this->assertTrue($res['ok']);
        $book = IOFactory::load($res['file']);
        $names = array_map(fn($s) => $s->getTitle(), $book->getAllSheets());
        $this->assertSame(['Sheet2','Sheet5','9394'], $names);
        $sheet2 = $book->getSheetByName('Sheet2');
        $this->assertSame(DataType::TYPE_STRING, $sheet2->getCell('A1')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet2->getCell('B1')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet2->getCell('C1')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet2->getCell('D1')->getDataType());
    }
}

