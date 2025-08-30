<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Perf;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Perf\Stopwatch;
use SmartAlloc\Perf\QueryCounter;

final class PerfBudgetSmokeTest extends TestCase
{
    public function testAdvisoryPerfBudgets(): void
    {
        QueryCounter::reset();

        $perf = Stopwatch::measure(function (): void {
            $sum = 0;
            for ($i = 0; $i < 1000; $i++) {
                $sum += $i;
            }
        });

        $this->assertLessThan(200, $perf->durationMs, 'Advisory p95 budget: 200ms');
        $this->assertLessThanOrEqual(10, QueryCounter::lastCount(), 'Advisory: ≤10 queries');
    }
}
