<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Testing\TestFilters;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\EventStoreInterface;
use SmartAlloc\Contracts\ScoringAllocatorInterface;


final class MemoryPressureTest extends TestCase
{
    protected function tearDown(): void
    {
        TestFilters::reset();
        parent::tearDown();
    }

    public function testMemoryMetricRecorded(): void
    {
        TestFilters::set(['memory_pressure_mb' => 1]);
        global $wpdb;
        $wpdb = new class extends wpdb {
            public string $prefix = 'wp_';
            public array $metrics = [];
            public function insert($table, $data){ if(str_contains($table,'metrics')){ $this->metrics[]=$data; } }
            public function query($q){ return true; }
            public function get_results($q,$t=ARRAY_A){ return []; }
            public function prepare(string $q, ...$a): string { return preg_replace('/%[dsf]/','x',$q); }
        };
        $logger = new Logging();
        $eventBus = new EventBus($logger, new class implements EventStoreInterface {
            public function insertEventIfNotExists(string $e, string $k, array $p): int { return 0; }
            public function startListenerRun(int $e, string $l): int { return 0; }
            public function finishListenerRun(int $l, string $s, ?string $err, int $d): void {}
            public function finishEvent(int $e, string $s, ?string $err, int $d): void {}
        });
        $metrics = new Metrics();
        $scorer = new class implements ScoringAllocatorInterface {
            public function rank(array $m, array $s): array { return $m; }
            public function score(array $m, array $s): float { return 1.0; }
        };
        $service = new AllocationService($logger,$eventBus,$metrics,$scorer,$wpdb);
        $student = ['student_id'=>1,'center_id'=>1,'gender'=>'M','id'=>1,'center'=>1];
        $service->assign($student);
        $this->assertNotEmpty($wpdb->metrics);
        $this->assertGreaterThan(0, $wpdb->metrics[0]['value']);
    }
}
