<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Export;

use SmartAlloc\Infra\Export\ExcelExporter;
use SmartAlloc\Infra\Export\CountersRepository;
use SmartAlloc\Tests\BaseTestCase;

final class ParallelExportTest extends BaseTestCase
{
    private function sampleRows(): array
    {
        return [ ['id' => 1, 'status' => 'allocated', 'reason_code' => '', 'fuzzy' => 0] ];
    }

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

    public function test_parallel_exports_with_isolated_processes(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available');
        }
        $dir = sys_get_temp_dir() . '/sa_parallel_' . uniqid();
        mkdir($dir);
        $children = 3;
        $pids = [];
        for ($i = 0; $i < $children; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $sub = $dir . '/' . getmypid();
                mkdir($sub);
                $exp = $this->createExporter($sub);
                $exp->exportFromRows($this->sampleRows());
                exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        $files = glob($dir . '/*/*.xlsx');
        $this->assertCount($children, $files, 'each process exports one file');
        foreach (glob($dir . '/*') as $sub) {
            array_map('unlink', glob($sub . '/*.xlsx'));
            rmdir($sub);
        }
        rmdir($dir);
    }
}
