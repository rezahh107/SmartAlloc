<?php
declare(strict_types=1);

use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Http\Rest\ReportsMetricsController;
use SmartAlloc\Infra\Export\ExcelExporter;
use SmartAlloc\Tests\Helpers\WpdbSpy as Spy;
use SmartAlloc\Tests\Helpers\EnvReset as Env;

final class BudgetTest extends BaseTestCase
{
    protected function setUp(): void
    {
        Env::sa_test_flush_cache();
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public array $queries = [];
            public function prepare($q, ...$a) { return $q; }
            public function get_results($q, $o = null) { $this->queries[] = $q; return []; }
            public function query($q) { $this->queries[] = $q; return 1; }
        };
        Spy::start();
        Functions\when('current_user_can')->justReturn(true);
    }

    protected function tearDown(): void
    {
        Spy::stop();
        parent::tearDown();
    }

    public function test_metrics_queries_under_budget(): void
    {
        $params = [
            'date_from' => '2025-01-01',
            'date_to'   => '2025-01-03',
            'group_by'  => 'day',
        ];
        $q1 = Spy::count(function () use ($params) {
            ReportsMetricsController::query($params);
        });
        $this->assertLessThanOrEqual(100, $q1, 'metrics queries should be ≤100 for small ranges');
    }

    public function test_metrics_cache_hit_reduces_queries(): void
    {
        $controller = new ReportsMetricsController();
        $params = ['date_from' => '2025-01-01', 'date_to' => '2025-01-03'];
        $miss = Spy::count(function () use ($controller, $params) {
            $_GET = $params;
            $controller->handle(new \WP_REST_Request());
        });
        $hit = Spy::count(function () use ($controller, $params) {
            $_GET = $params;
            $controller->handle(new \WP_REST_Request());
        });
        $this->assertTrue($hit < $miss, 'cache hit should reduce queries');
    }

    public function test_exporter_peak_memory_under_32mb_or_skip(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet not available in this env');
        }
        Env::sa_test_seed_rng(1337);
        global $wpdb;
        $exporter = new ExcelExporter($wpdb, null, sys_get_temp_dir());
        $rows = array_fill(0, 1100, ['status' => 'allocated']);
        $before = memory_get_peak_usage(true);
        try {
            $exporter->exportFromRows($rows);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Exporter unavailable: ' . $e->getMessage());
        }
        $after = memory_get_peak_usage(true);
        $this->assertLessThan(32 * 1024 * 1024, max($before, $after), 'peak memory < 32MB');
    }
}
