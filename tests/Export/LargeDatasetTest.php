<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Export;

use SmartAlloc\Infra\Export\ExcelExporter;
use SmartAlloc\Infra\Export\CountersRepository;
use SmartAlloc\Tests\BaseTestCase;

final class LargeDatasetTest extends BaseTestCase
{
    private function createExporter(string $dir): ExcelExporter
    {
        $configPath = dirname(__DIR__, 2) . '/SmartAlloc_Exporter_Config_v1.json';
        $repo = new class extends CountersRepository {
            private int $d = 0; private int $b = 0;
            public function __construct() {}
            public function getNextCounters(): array { $this->d++; $this->b++; return [$this->d, $this->b]; }
        };
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function prepare($q, ...$a) { return $q; }
            public function get_results($q, $mode) { return []; }
        };
        return new ExcelExporter($wpdb, $configPath, $dir, $repo);
    }

    public function test_export_handles_large_dataset(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet not installed');
        }
        if (!method_exists(\PhpOffice\PhpSpreadsheet\Settings::class, 'setCacheStorageMethod')) {
            $this->markTestSkipped('Caching API unavailable in this library version');
        }
        $dir = sys_get_temp_dir();
        $exporter = $this->createExporter($dir);
        $rows = array_fill(0, 10000, ['id'=>1,'status'=>'allocated','reason_code'=>'','fuzzy'=>0]);
        $before = memory_get_peak_usage(true);
        $result = $exporter->exportFromRows($rows);
        $after = memory_get_peak_usage(true);
        $this->assertFileExists($result['path']);
        $this->assertLessThan(512 * 1024 * 1024, $after, 'peak memory <512MB');
        unlink($result['path']);
    }
}
