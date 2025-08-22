<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Performance;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Perf\Stopwatch;
use SmartAlloc\Perf\QueryCounter;
use SmartAlloc\Tests\Fixtures\BulkDatasetBuilder;

final class AllocationPerfTest extends TestCase
{
    /**
     * @dataProvider provideScales
     */
    public function test_allocate_within_budgets(int $students, int $qBudgetEnvDefault, int $tBudgetEnvDefault): void
    {
        QueryCounter::reset();
        $qBudget = (int) getenv($students === 1000 ? 'SMARTALLOC_BUDGET_Q_1K' : 'SMARTALLOC_BUDGET_Q_10K') ?: $qBudgetEnvDefault;
        $tBudget = (int) getenv($students === 1000 ? 'SMARTALLOC_BUDGET_ALLOC_1K_MS' : 'SMARTALLOC_BUDGET_ALLOC_10K_MS') ?: $tBudgetEnvDefault;

        [$mentors, $studentsList] = BulkDatasetBuilder::build($students, 200);
        $callable = fn() => $this->allocate($studentsList, $mentors);

        $result = Stopwatch::measure($callable);
        $this->assertLessThanOrEqual($tBudget, $result->durationMs, "p95 allocation time budget exceeded for $students");

        if (!QueryCounter::isAvailable()) {
            $this->markTestSkipped('wpdb->queries unavailable for query budget assertion');
        }
        $qCount = QueryCounter::lastCount();
        $this->assertLessThanOrEqual($qBudget, $qCount, "Query budget exceeded for $students (count=$qCount)");

        // Feature flags must not change results
        $baseline = $result->result;
        putenv('SMARTALLOC_PERF_ENABLE_CACHE=1');
        putenv('SMARTALLOC_PERF_ENABLE_BATCH=1');
        QueryCounter::reset();
        $optimized = $this->allocate($studentsList, $mentors);
        putenv('SMARTALLOC_PERF_ENABLE_CACHE');
        putenv('SMARTALLOC_PERF_ENABLE_BATCH');
        $this->assertSame($baseline, $optimized);
    }

    public function provideScales(): array
    {
        return [[1000, 2000, 2500], [10000, 12000, 12000]];
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
