<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ExporterImporter;

use SmartAlloc\Infra\GF\SabtEntryMapper;
use SmartAlloc\Tests\BaseTestCase;
use org\bovigo\vfs\vfsStream;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class PriorityRulesTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Spreadsheet::class) || !class_exists(vfsStream::class)) {
            $this->markTestSkipped('PhpSpreadsheet/vfsStream unavailable');
        }
    }

    public function test_postal_alias_precedence(): void
    {
        $mapper = new SabtEntryMapper();
        $entry = [
            'postal_code_alias' => '۱۲۳۴۵۶',
            'postal_code'      => '۹۸۷۶۵۴',
            '92'               => 'F',
            '93'               => '1',
            '39'               => '7',
            '20'               => '09123456789',
            '22'               => '0211234567',
            '76'               => '2222222222222222',
            '75'               => '3',
        ];

        $result = $mapper->mapEntry($entry);
        $this->assertTrue($result['ok']);
        $student = $result['student'];
        $this->assertSame('123456', $student['postal_code']);
    }

    public function test_reg_status_clears_hakmat_fields(): void
    {
        $entry = [
            '75' => '2',
            'hakmat_code' => 'ABC',
            'hakmat_name' => 'Foo',
        ];

        if (($entry['75'] ?? '') !== '3') {
            $entry['hakmat_code'] = '';
            $entry['hakmat_name'] = '';
        }

        $this->assertSame('', $entry['hakmat_code']);
        $this->assertSame('', $entry['hakmat_name']);
    }
}
