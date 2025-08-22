<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ExporterImporter;

use SmartAlloc\Tests\BaseTestCase;
use org\bovigo\vfs\vfsStream;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use SmartAlloc\Services\ExportService;
use ReflectionClass;

final class NormalizerTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Spreadsheet::class) || !class_exists(vfsStream::class)) {
            $this->markTestSkipped('PhpSpreadsheet/vfsStream unavailable');
        }
    }

    private function callNormalizeValue(mixed $value, array $config): mixed
    {
        $ref = new ReflectionClass(ExportService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $m = $ref->getMethod('normalizeValue');
        $m->setAccessible(true);
        return $m->invoke($svc, $value, $config);
    }

    public function test_digits10_normalizer(): void
    {
        $persian = '۰۱۲۳۴۵۶۷۸۹';
        $normalized = $this->callNormalizeValue($persian, ['normalize' => ['digits_10']]);
        $this->assertSame('0123456789', $normalized);
    }

    public function test_digits16_normalizer(): void
    {
        $persian = '۰۱۲۳۴۵۶۷۸۹۰۱۲۳۴۵۶۷۸';
        $normalized = $this->callNormalizeValue($persian, ['normalize' => ['digits_16']]);
        $this->assertSame('0123456789012345', $normalized);
    }

    public function test_mobile_ir_normalizer_valid(): void
    {
        $normalized = $this->callNormalizeValue('0912 345 6789', ['normalize' => ['mobile_ir']]);
        $this->assertSame('09123456789', $normalized);
    }

    public function test_mobile_ir_normalizer_invalid(): void
    {
        $normalized = $this->callNormalizeValue('12345', ['normalize' => ['mobile_ir']]);
        $this->assertSame('0912345', $normalized);
    }
}
