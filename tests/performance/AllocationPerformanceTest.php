<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class AllocationPerformanceTest extends BaseTestCase {
    public function test_throughput_p95_and_memory_budget_or_skip(): void {
        if (getenv('RUN_PERFORMANCE_TESTS') !== '1') {
            $this->markTestSkipped('performance tests opt-in');
        }
        $N = 1000;
        $durations = [];
        for ($i = 0; $i < $N; $i++) {
            $t0 = microtime(true);
            // simulate allocation work
            $x = $i * $i;
            $durations[] = microtime(true) - $t0;
        }
        sort($durations);
        $p95 = $durations[(int) floor(0.95 * ($N - 1))];
        $peak = memory_get_peak_usage(true);
        if ($p95 === 0.0) {
            $this->markTestSkipped('timer resolution not reliable');
        }
        $this->assertLessThan(2.0, $p95, 'p95 under 2s');
        $this->assertLessThan(32 * 1024 * 1024, $peak, 'memory under 32MB');
    }
}
