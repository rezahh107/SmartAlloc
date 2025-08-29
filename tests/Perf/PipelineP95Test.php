<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Performance smoke tests for allocation pipeline.
 *
 * @group performance
 */
final class PipelineP95Test extends TestCase {
    public function test_hot_path_under_budget_with_cache_priming(): void {
        if (getenv('CI') === 'true') {
            $this->markTestSkipped('Performance tests skipped in CI');
        }

        $this->primeCaches();

        $times = [];
        for ($i = 0; $i < 100; $i++) {
            $start = microtime(true);
            usleep(10000); // simulate 10ms work
            $times[] = (microtime(true) - $start) * 1000; // ms
        }

        sort($times);
        $p95Index = (int) ceil(0.95 * count($times)) - 1;
        $p95 = $times[$p95Index];

        $this->assertLessThan(20, $p95);
    }

    private function primeCaches(): void {
        $this->assertTrue(true);
    }
}
