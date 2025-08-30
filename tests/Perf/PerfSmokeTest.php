<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Perf\Stopwatch;
use SmartAlloc\Perf\QueryCounter;

final class PerfSmokeTest extends TestCase
{
    public function testPerfBudgetsAreReasonable(): void
    {
        QueryCounter::reset();
        $result = Stopwatch::measure(function (): void {
            $sum = 0;
            for ($i = 0; $i < 1000; $i++) {
                $sum += $i;
            }
        });
        $this->assertLessThan(200, $result->durationMs, 'p95 budget 200ms (advisory)');
        $this->assertLessThanOrEqual(10, QueryCounter::lastCount(), '≤10 queries');
    }
}
