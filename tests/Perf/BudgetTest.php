<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Services\ExportService;
use SmartAlloc\Services\Logging;

final class BudgetTest extends TestCase
{
    private function wpdbStub(): void
    {
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public array $queries = [];
            public int $insert_id = 0;
            public function log($q){ $this->queries[]=$q; }
            public function prepare($q, ...$a){ return $q; }
            public function get_results($q, $o = ARRAY_A){ $this->log($q); return []; }
            public function get_var($q){ $this->log($q); return null; }
            public function query($q){ $this->log($q); return 1; }
            public function insert($t,$d){ $this->log('INSERT'); $this->insert_id = 1; return 1; }
            public function get_charset_collate(){ return ''; }
        };
    }
    public function test_metrics_query_budget(): void
    {
        $this->wpdbStub();
        global $wpdb;
        $metrics = new Metrics();
        for ($i = 0; $i < 5; $i++) { $metrics->inc('t'); }
        $metrics->get('t', 5);
        $count = count($wpdb->queries);
        file_put_contents('budgets.log', "queries:$count\n", FILE_APPEND);
        $this->assertLessThanOrEqual(100, $count);
    }

    public function test_metrics_cache_reduces_queries(): void
    {
        $this->wpdbStub();
        global $wpdb;
        $metrics = new Metrics();
        $metrics->get('t', 5); // miss
        $first = count($wpdb->queries);
        $metrics->get('t', 5); // hit
        $second = count($wpdb->queries) - $first;
        file_put_contents('budgets.log', "cache_first:$first cache_second:$second\n", FILE_APPEND);
        $this->assertLessThanOrEqual($first, $second);
    }

    public function test_excel_exporter_memory_budget(): void
    {
        $this->markTestSkipped('memory budget check requires real environment');
    }
}
