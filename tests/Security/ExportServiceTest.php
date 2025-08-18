<?php
use PHPUnit\Framework\TestCase;
use SmartAlloc\Infra\Export\ExporterService;

final class ExportServiceTest extends TestCase
{
    public function testSQLInjectionProtection(): void
    {
        $service = new ExporterService();
        $this->expectException(InvalidArgumentException::class);
        $service->exportData('1; DROP TABLE exports;');
    }
}
