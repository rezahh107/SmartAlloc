<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Regression;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Perf\QueryCounter;
use SmartAlloc\Tests\Fixtures\BulkDatasetBuilder;

final class QueryPlanGuardTest extends BaseTestCase
{
    public function test_allocation_does_not_exhibit_n_plus_1(): void
    {
        if (!QueryCounter::isAvailable()) {
            $this->markTestSkipped('wpdb->queries unavailable');
        }

        $Ns = [100, 500, 1000];
        $counts = [];
        foreach ($Ns as $N) {
            QueryCounter::reset();
            [$mentors, $students] = BulkDatasetBuilder::build($N, 50);
            $this->allocate($students, $mentors);
            $counts[$N] = QueryCounter::lastCount();
        }

        $slope = $this->estimateSlope($counts);
        $this->assertLessThan(5.0, $slope, "Possible N+1 detected (slope=$slope). Consider batching/cache/preloading.");

        // Feature flags do not change results
        [$mentors, $students] = BulkDatasetBuilder::build(200, 20);
        $baseline = $this->allocate($students, $mentors);
        putenv('SMARTALLOC_PERF_ENABLE_CACHE=1');
        putenv('SMARTALLOC_PERF_ENABLE_BATCH=1');
        $optimized = $this->allocate($students, $mentors);
        putenv('SMARTALLOC_PERF_ENABLE_CACHE');
        putenv('SMARTALLOC_PERF_ENABLE_BATCH');
        $this->assertSame($baseline, $optimized);
    }

    private function estimateSlope(array $counts): float
    {
        $xs = array_keys($counts);
        $ys = array_values($counts);
        $n = count($xs);
        $sumX = array_sum($xs);
        $sumY = array_sum($ys);
        $sumXY = 0;
        $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $xs[$i] * $ys[$i];
            $sumX2 += $xs[$i] * $xs[$i];
        }
        return ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    }

    private function allocate(array $students, array $mentors): array
    {
        global $wpdb;
        $mentorCount = count($mentors);
        $i = 0;
        foreach ($students as $_) {
            $wpdb->get_results('SELECT * FROM mentors');
            $mentors[$i % $mentorCount]['assigned']++;
            $i++;
        }
        return $mentors;
    }
}
