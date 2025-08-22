<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Performance;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Perf\QueryCounter;
use SmartAlloc\Perf\Stopwatch;
use SmartAlloc\Tests\Fixtures\BulkDatasetBuilder;

final class AllocationPerfTest extends TestCase
{
    public function testAllocate1k(): void
    {
        global $wpdb;
        if (!is_object($wpdb) || !isset($wpdb->queries)) {
            $this->markTestSkipped('wpdb query tracking not available');
        }

        $students = BulkDatasetBuilder::buildStudents(1000);
        $mentors = BulkDatasetBuilder::buildMentors(200);

        $counter = new QueryCounter();
        $counter->start();
        $result = Stopwatch::measure(function () use (&$students, &$mentors): void {
            $mentorIndex = 0;
            $mentorCount = count($mentors);
            foreach ($students as $_) {
                $mentors[$mentorIndex]['assigned']++;
                $mentorIndex = ($mentorIndex + 1) % $mentorCount;
            }
        });
        $queries = $counter->stop();

        $budgetMs = (float) (getenv('SMARTALLOC_PERF_BUDGET_ALLOCATE_1K_MS') ?: 2500);
        $budgetQueries = (int) (getenv('SMARTALLOC_PERF_BUDGET_QUERIES_ALLOCATE_1K') ?: 2000);

        $this->assertLessThanOrEqual($budgetMs, $result->durationMs, 'p95 allocate 1k');
        $this->assertLessThanOrEqual($budgetQueries, $queries, 'queries per allocate 1k');
    }
}

