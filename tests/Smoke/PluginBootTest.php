<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class PluginBootTest extends TestCase
{
    public function testCoreFilesExist(): void
    {
        $basePath = dirname(__DIR__, 2) . '/src';

        $this->assertFileExists(
            $basePath . '/Services/AllocationService.php',
            'AllocationService.php must exist'
        );

        $this->assertFileExists(
            $basePath . '/Infra/Export/ExcelExporter.php',
            'ExcelExporter.php must exist'
        );
    }
}
