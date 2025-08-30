<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Performance;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Perf\Stopwatch;
use SmartAlloc\Perf\QueryCounter;
use SmartAlloc\RuleEngine\RuleEngineService;
use SmartAlloc\Tests\Fixtures\BulkDatasetBuilder;

final class BudgetTest extends BaseTestCase
{
    public function testAllocationWithinBudget(): void
    {
        QueryCounter::reset();
        $students = BulkDatasetBuilder::buildStudents(200);
        $engine = new RuleEngineService();

        $result = Stopwatch::measure(function () use ($engine, $students) {
            foreach ($students as $s) {
                $engine->evaluate($s);
            }
            return true;
        });

        $queries = QueryCounter::lastCount();
        $this->assertLessThan(200, $result->durationMs, 'p95 budget exceeded');
        $this->assertLessThanOrEqual(10, $queries, 'Query budget exceeded');
    }
}
