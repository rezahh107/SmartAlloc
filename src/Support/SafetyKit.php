<?php // phpcs:ignoreFile

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
 * Lightweight circuit breaker for basic safety operations.
 */
class SimpleCircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN   = 'open';

    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $failureCount = 0;
    private ?int $lastFailureTime = null;
    private string $state = self::STATE_CLOSED;
    private string $name;

    public function __construct(int $failureThreshold = 3, int $recoveryTimeout = 30, string $name = 'simple')
    {
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout  = $recoveryTimeout;
        $this->name             = function_exists('sanitize_key') ? sanitize_key($name) : $name;
    }

    /**
     * Execute an operation under circuit protection.
     *
     * @param callable $operation Operation to execute.
     * @param mixed    ...$args   Arguments for the operation.
     *
     * @return mixed
     * @throws \Exception When circuit is open or operation fails.
     */
    public function execute(callable $operation, ...$args)
    {
        if ($this->isOpen()) {
            if ($this->shouldReset()) {
                $this->state       = self::STATE_CLOSED;
                $this->failureCount = 0;
            } else {
                $msg = sprintf('Simple circuit breaker "%s" is open. Operation blocked.', $this->name);
                $msg = function_exists('esc_html') ? esc_html($msg) : $msg;
                throw new \Exception($msg);
            }
        }

        try {
            $result = $operation(...$args);
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure($e);
            throw $e;
        }
    }

    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    private function shouldReset(): bool
    {
        if ($this->lastFailureTime === null) {
            return false;
        }
        $now = $this->getTime();
        return ($now - $this->lastFailureTime) >= $this->recoveryTimeout;
    }

    private function onSuccess(): void
    {
        if ($this->state === self::STATE_OPEN) {
            $this->state         = self::STATE_CLOSED;
            $this->failureCount  = 0;
            $this->lastFailureTime = null;
        }
    }

    private function onFailure(\Throwable $e): void
    {
        $this->failureCount++;
        $this->lastFailureTime = $this->getTime();
        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
        }
        if (function_exists('error_log')) {
            $msg = sprintf('SimpleCircuitBreaker "%s" failure: %s', $this->name, $e->getMessage());
            $msg = function_exists('esc_html') ? esc_html($msg) : $msg;
            error_log($msg);
        }
    }

    private function getTime(): int
    {
        if (function_exists('wp_date')) {
            return (int) wp_date('U');
        }
        return time();
    }

    /**
     * Reset circuit to closed state.
     */
    public function reset(): void
    {
        $this->state          = self::STATE_CLOSED;
        $this->failureCount   = 0;
        $this->lastFailureTime = null;
    }

    /**
     * Get circuit statistics.
     *
     * @return array<string,mixed>
     */
    public function getStatistics(): array
    {
        return [
            'name'             => $this->name,
            'state'            => $this->state,
            'failure_count'    => $this->failureCount,
            'failure_threshold'=> $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
            'last_failure_time'=> $this->lastFailureTime,
            'type'             => 'simple',
        ];
    }

    public function getStatusMessage(): string
    {
        $msg = sprintf(
            'Circuit "%s" is %s (failures: %d/%d)',
            $this->name,
            $this->state,
            $this->failureCount,
            $this->failureThreshold
        );
        return function_exists('esc_html') ? esc_html($msg) : $msg;
    }
}

/**
 * SafetyKit helper utilities.
 */
class SafetyKit
{
    public static function createSimpleCircuitBreaker(string $name = 'default', int $failureThreshold = 3, int $recoveryTimeout = 30): SimpleCircuitBreaker
    {
        return new SimpleCircuitBreaker($failureThreshold, $recoveryTimeout, $name);
    }

    public static function safeExecute(callable $operation, string $circuitName = 'safe_execute', int $failureThreshold = 3, int $recoveryTimeout = 30, ...$args)
    {
        static $circuits = [];
        $key = function_exists('sanitize_key') ? sanitize_key($circuitName) : $circuitName;
        if (!isset($circuits[$key])) {
            $circuits[$key] = new SimpleCircuitBreaker($failureThreshold, $recoveryTimeout, $key);
        }
        try {
            return $circuits[$key]->execute($operation, ...$args);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'circuit breaker')) {
                return null;
            }
            throw $e;
        }
    }
}
