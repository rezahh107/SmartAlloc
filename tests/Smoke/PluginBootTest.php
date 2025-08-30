<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PluginBootTest extends TestCase
{
    public function testProjectHasCoreFiles(): void
    {
        $this->assertFileExists(__DIR__ . '/../../src/Services/AllocationService.php');
        $this->assertFileExists(__DIR__ . '/../../src/Infra/Export/ExcelExporter.php');
    }
}
