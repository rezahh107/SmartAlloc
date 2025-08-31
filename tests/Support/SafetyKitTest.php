<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Support\{
    Stopwatch,
    PerfSamples,
    PerfBudget,
    RuleEngineResult,
    FailureMapper,
    CircuitBreaker,
    BudgetExceededException,
    CircuitOpenException
};

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        global $filters;
        return $filters[$tag] ?? $value;
    }
}

final class SafetyKitTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        if (function_exists('remove_all_filters')) {
            remove_all_filters('smartalloc_perf_budgets');
        }
    }

    public function test_stopwatch_measures_time_and_returns_result(): void
    {
        [$res, $ms] = Stopwatch::time(fn () => usleep(1000) ?: 42);
        $this->assertSame(42, $res);
        $this->assertGreaterThan(0.0, $ms);
    }

    public function test_stopwatch_propagates_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        Stopwatch::time(fn () => throw new \RuntimeException('boom'));
    }

    public function test_perf_samples_and_percentile_with_limit(): void
    {
        for ($i = 0; $i < 101; $i++) {
            PerfSamples::add('m', (float) $i);
        }
        $this->assertSame(95.0, PerfSamples::percentile('m', 95));
    }

    public function test_perf_samples_empty_returns_zero(): void
    {
        $this->assertSame(0.0, PerfSamples::percentile('none', 95));
    }

    public function test_perf_budget_enforces_threshold(): void
    {
        PerfSamples::add('allocate_ms_p95', 2500.0);
        $this->expectException(BudgetExceededException::class);
        PerfBudget::enforce('allocate_ms_p95');
    }

    public function test_rule_engine_result_to_array(): void
    {
        $r = new RuleEngineResult(RuleEngineResult::OK, 'ok', ['x' => 1]);
        $this->assertSame(
            ['status' => 'ok', 'message' => 'ok', 'meta' => ['x' => 1]],
            $r->toArray()
        );
    }

    public function test_failure_mapper_preserves_data(): void
    {
        $e = new \InvalidArgumentException('bad');
        $r = FailureMapper::from($e);
        $this->assertSame(RuleEngineResult::INVALID, $r->status);
        $this->assertSame('bad', $r->message);
        $this->assertSame(\InvalidArgumentException::class, $r->meta['exception']);
    }

    public function test_circuit_breaker_opens_and_resets(): void
    {
        try {
            CircuitBreaker::call('svc', fn () => throw new \RuntimeException('fail'), 1, 1);
        } catch (\RuntimeException $e) {
        }
        $this->expectException(CircuitOpenException::class);
        CircuitBreaker::call('svc', fn () => true, 1, 1);
        sleep(1);
        $this->assertTrue(CircuitBreaker::call('svc', fn () => true, 1, 1));
    }
}
