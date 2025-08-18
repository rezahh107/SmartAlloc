<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Export;

use SmartAlloc\Infra\Export\ExcelExporter;
use SmartAlloc\Infra\Export\CountersRepository;
use SmartAlloc\Tests\BaseTestCase;

final class ExcelExporterCountersTest extends BaseTestCase
{
    private function sampleRows(): array
    {
        return [
            ['id' => 1, 'status' => 'allocated', 'reason_code' => '', 'fuzzy' => 0],
        ];
    }

    private function createExporter(CountersRepository $repo): ExcelExporter
    {
        $wpdb = $this->mockWpdb($this->sampleRows());
        $configPath = dirname(__DIR__, 2) . '/SmartAlloc_Exporter_Config_v1.json';
        return new ExcelExporter($wpdb, $configPath, sys_get_temp_dir(), $repo);
    }

    public function test_daily_counter_increments_and_resets_on_date_change(): void
    {
        $repo = new class extends CountersRepository {
            private string $date = '2024_01_01';
            private string $last = '';
            public int $daily = 0;
            public int $batch = 0;
            public function __construct() {}
            public function setDate(string $d): void { $this->date = $d; }
            public function getNextCounters(): array {
                if ($this->last !== $this->date) { $this->daily = 0; $this->last = $this->date; }
                $this->daily++; $this->batch++;
                return [$this->daily, $this->batch];
            }
        };
        $exporter = $this->createExporter($repo);
        $res1 = $exporter->exportFromRows($this->sampleRows());
        $this->assertSame(1, $repo->daily);
        $res2 = $exporter->exportFromRows($this->sampleRows());
        $this->assertSame(2, $repo->daily);
        $repo->setDate('2024_01_02');
        $res3 = $exporter->exportFromRows($this->sampleRows());
        $this->assertSame(1, $repo->daily);
        unlink($res1['path']); unlink($res2['path']); unlink($res3['path']);
    }

    public function test_batch_counter_increments_monotonically(): void
    {
        $repo = new class extends CountersRepository {
            private string $date = '2024_01_01';
            private string $last = '';
            public int $daily = 0;
            public int $batch = 0;
            public function __construct() {}
            public function setDate(string $d): void { $this->date = $d; }
            public function getNextCounters(): array {
                if ($this->last !== $this->date) { $this->daily = 0; $this->last = $this->date; }
                $this->daily++; $this->batch++;
                return [$this->daily, $this->batch];
            }
        };
        $exporter = $this->createExporter($repo);
        $r1 = $exporter->exportFromRows($this->sampleRows());
        $this->assertSame(1, $repo->batch);
        $r2 = $exporter->exportFromRows($this->sampleRows());
        $this->assertSame(2, $repo->batch);
        $repo->setDate('2024_01_02');
        $r3 = $exporter->exportFromRows($this->sampleRows());
        $this->assertSame(3, $repo->batch);
        unlink($r1['path']); unlink($r2['path']); unlink($r3['path']);
    }

    public function test_filename_reflects_counters(): void
    {
        $repo = new class extends CountersRepository {
            public int $daily = 0; public int $batch = 0; public function __construct() {}
            public function getNextCounters(): array { $this->daily++; $this->batch++; return [$this->daily,$this->batch]; }
        };
        $exporter = $this->createExporter($repo);
        $res = $exporter->exportFromRows($this->sampleRows());
        $file = basename($res['path']);
        $this->assertMatchesRegularExpression('/-0*' . $repo->daily . '-B0*' . $repo->batch . '\.xlsx$/', $file);
        unlink($res['path']);
    }

    private function mockWpdb(array $results)
    {
        return new class($results) {
            public $prefix = 'wp_';
            public function __construct(private array $results) {}
            public function prepare($query, ...$args) { return vsprintf($query, $args); }
            public function get_results($sql, $mode) { return $this->results; }
        };
    }
}
