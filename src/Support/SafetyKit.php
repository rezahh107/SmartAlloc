<?php

declare(strict_types=1);

namespace SmartAlloc\Support;

use RuntimeException;

/**
 * Lightweight safety utilities for timing, performance budgets,
 * structured error handling and circuit breaking.
 */
class Stopwatch
{
    /**
     * Measure execution time for a callable.
     *
     * @param callable $fn Callable to execute.
     * @return array{mixed,float} Result and elapsed milliseconds.
     *
     * @throws \Throwable When the callable throws.
     */
    public static function time(callable $fn): array
    {
        $start = microtime(true);
        try {
            $result = $fn();
        } catch (\Throwable $e) {
            $ms = (microtime(true) - $start) * 1000;
            throw $e;
        }
        $ms = (microtime(true) - $start) * 1000;
        return [$result, $ms];
    }
}

/**
 * Collect in-memory performance samples.
 */
class PerfSamples
{
    /** @var array<string,list<float>> */
    private static array $samples = [];

    /**
     * Add a sample value for the given metric.
     */
    public static function add(string $metric, float $value): void
    {
        if (! isset(self::$samples[$metric])) {
            self::$samples[$metric] = [];
        }
        self::$samples[$metric][] = $value;
        if (count(self::$samples[$metric]) > 100) {
            array_shift(self::$samples[$metric]);
        }
    }

    /**
     * Get a percentile for a metric.
     */
    public static function percentile(string $metric, int $p): float
    {
        $values = self::$samples[$metric] ?? [];
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $index = (int) ceil(count($values) * $p / 100) - 1;
        return $values[$index] ?? 0.0;
    }
}

/**
 * Exception thrown when a performance budget is exceeded.
 */
class BudgetExceededException extends RuntimeException
{
}

/**
 * Enforce performance budgets based on collected samples.
 */
class PerfBudget
{
    /**
     * Ensure the given metric stays under its budget.
     *
     * @throws BudgetExceededException When threshold exceeded.
     */
    public static function enforce(string $metric): void
    {
        $budgets = apply_filters('smartalloc_perf_budgets', [
            'allocate_ms_p95' => 2000,
        ]);
        $threshold = $budgets[$metric] ?? PHP_FLOAT_MAX;
        $actual    = PerfSamples::percentile($metric, 95);
        if ($actual > $threshold) {
            /* phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped */
            throw new BudgetExceededException(
                sprintf(
                    'Performance budget exceeded: %s=%sms > %sms',
                    $metric,
                    $actual,
                    $threshold
                )
            );
            /* phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped */
        }
    }
}

/**
 * Structured result for rule engine operations.
 */
class RuleEngineResult
{
    public const OK      = 'ok';
    public const INVALID = 'invalid';
    public const FAILED  = 'failed';

    /**
     * @param string               $status  Result status.
     * @param string               $message Human-readable message.
     * @param array<string,mixed>  $meta    Additional data.
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly array $meta = [],
    ) {
    }

    /**
     * Serialize to array.
     *
     * @return array{status:string,message:string,meta:array<string,mixed>}
     */
    public function toArray(): array
    {
        return [
            'status'  => $this->status,
            'message' => $this->message,
            'meta'    => $this->meta,
        ];
    }
}

/**
 * Map failures to RuleEngineResult instances.
 */
class FailureMapper
{
    /**
     * Create a result from an exception.
     */
    public static function from(\Throwable $e): RuleEngineResult
    {
        $status = $e instanceof \InvalidArgumentException ? RuleEngineResult::INVALID : RuleEngineResult::FAILED;
        return new RuleEngineResult($status, $e->getMessage(), [ 'exception' => get_class($e) ]);
    }
}

/**
 * Exception thrown when a circuit is open.
 */
class CircuitOpenException extends RuntimeException
{
}

/**
 * Simple circuit breaker implementation.
 */
class CircuitBreaker
{
    /** @var array<string,int> */
    private static array $failures = [];
    /** @var array<string,int> */
    private static array $openUntil = [];

    /**
     * Execute a service call with circuit breaking.
     *
     * @template T
     * @param string   $service   Service identifier.
     * @param callable $fn        Callable to execute.
     * @param int      $threshold Failure threshold before opening circuit.
     * @param int      $cooldown  Cooldown in seconds.
     *
     * @return mixed
     *
     * @throws CircuitOpenException When circuit is open.
     */
    public static function call(string $service, callable $fn, int $threshold = 5, int $cooldown = 120)
    {
        $now = time();
        if (isset(self::$openUntil[$service]) && $now < self::$openUntil[$service]) {
            throw new CircuitOpenException("Circuit open for {$service}"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        try {
            $result = $fn();
            self::$failures[$service]  = 0;
            unset(self::$openUntil[$service]);
            return $result;
        } catch (\Throwable $e) {
            self::$failures[$service] = (self::$failures[$service] ?? 0) + 1;
            if (self::$failures[$service] >= $threshold) {
                self::$openUntil[$service] = $now + $cooldown;
            }
            throw $e;
        }
    }
}
