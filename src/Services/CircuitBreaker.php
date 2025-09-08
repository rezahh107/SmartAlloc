<?php

/**
 * Circuit Breaker Service for SmartAlloc
 *
 * Implements circuit breaker pattern for fault tolerance
 * and system resilience in allocation operations.
 *
 * @package SmartAlloc
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Interfaces\CircuitBreakerInterface;
use SmartAlloc\Exceptions\CircuitBreakerException;
use RuntimeException;
use Throwable;

/**
 * Circuit Breaker implementation
 *
 * Provides fault tolerance by monitoring failure rates
 * and preventing cascading failures in the system.
 */
class CircuitBreaker implements CircuitBreakerInterface
{
    /**
     * Circuit breaker states
     */
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    /**
     * Current circuit state
     *
     * @var string
     */
    private string $state;

    /**
     * Failure count threshold
     *
     * @var int
     */
    private int $failureThreshold;

    /**
     * Recovery timeout in seconds
     *
     * @var int
     */
    private int $recoveryTimeout;

    /**
     * Current failure count
     *
     * @var int
     */
    private int $failureCount;

    /**
     * Last failure timestamp
     *
     * @var int|null
     */
    private ?int $lastFailureTime;

    /**
     * Half-open state callback
     *
     * @var callable|null
     */
    private $halfOpenCallback;

    /**
     * Success count in half-open state
     *
     * @var int
     */
    private int $halfOpenSuccessCount;

    /**
     * Required successes to close circuit
     *
     * @var int
     */
    private int $halfOpenSuccessThreshold;

    /**
     * Constructor
     *
     * @param int           $failureThreshold         Failure count threshold.
     * @param int           $recoveryTimeout          Recovery timeout in seconds.
     * @param callable|null $halfOpenCallback         Optional callback for half-open state.
     * @param int           $halfOpenSuccessThreshold Required successes to close circuit.
     */
    public function __construct(
        $failureThreshold = 5,
        int $recoveryTimeout = 60,
        ?callable $halfOpenCallback = null,
        int $halfOpenSuccessThreshold = 3
    ) {
        if (is_string($failureThreshold)) {
            $key                   = $failureThreshold;
            $this->failureThreshold = (int) apply_filters('smartalloc_cb_threshold', 5, $key);
            $this->recoveryTimeout  = (int) apply_filters('smartalloc_cb_cooldown', 60, $key);
        } else {
            $this->failureThreshold = (int) $failureThreshold;
            $this->recoveryTimeout  = $recoveryTimeout;
        }

        $this->halfOpenCallback         = $halfOpenCallback;
        $this->halfOpenSuccessThreshold = $halfOpenSuccessThreshold;
        $this->state                    = self::STATE_CLOSED;
        $this->failureCount             = 0;
        $this->lastFailureTime          = null;
        $this->halfOpenSuccessCount     = 0;
    }

    /**
     * Execute operation with circuit breaker protection
     *
     * @param callable $operation Operation to execute.
     * @param mixed    ...$args   Operation arguments.
     *
     * @return mixed Operation result.
     * @throws CircuitBreakerException When circuit is open.
     */
    public function execute(callable $operation, ...$args)
    {
        if ($this->isOpen()) {
            if ($this->shouldAttemptReset()) {
                $this->transitionToHalfOpen();
            } else {
                throw new CircuitBreakerException(
                    'Circuit breaker is open. Operation blocked.'
                );
            }
        }

        try {
            $result = $operation(...$args);
            $this->onSuccess();
            return $result;
        } catch (\Throwable $exception) {
            $this->onFailure();
            throw $exception;
        }
    }

    /**
     * Check if circuit breaker is open
     *
     * @return bool True if circuit is open.
     */
    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    /**
     * Check if circuit breaker is closed
     *
     * @return bool True if circuit is closed.
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Check if circuit breaker is half-open
     *
     * @return bool True if circuit is half-open.
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    /**
     * Get current circuit state
     *
     * @return string Current state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get current failure count
     *
     * @return int Failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get failure threshold
     *
     * @return int Failure threshold.
     */
    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    /**
     * Reset circuit breaker to closed state
     *
     * @return void
     */
    public function reset(): void
    {
        $this->state                = self::STATE_CLOSED;
        $this->failureCount         = 0;
        $this->lastFailureTime      = null;
        $this->halfOpenSuccessCount = 0;
    }

    public function getStatus(): CircuitBreakerStatus
    {
        $cooldownUntil = $this->lastFailureTime !== null
            ? $this->lastFailureTime + $this->recoveryTimeout
            : null;

        return new CircuitBreakerStatus(
            $this->state,
            $this->failureCount,
            $this->failureThreshold,
            $cooldownUntil,
            null
        );
    }

    public function recordFailure(string $error): void
    {
        unset($error);
        $this->onFailure();
    }

    public function recordSuccess(): void
    {
        $this->onSuccess();
    }

    public function guard(string $context): void
    {
        if ($this->isOpen()) {
            throw new RuntimeException('Circuit breaker open for ' . $context);
        }
    }

    public function success(string $context): void
    {
        unset($context);
        $this->onSuccess();
    }

    public function failure(string $context, Throwable $exception): void
    {
        unset($context);
        $this->recordFailure($exception->getMessage());
    }

    /**
     * Force circuit breaker to open state
     *
     * @return void
     */
    public function forceOpen(): void
    {
        $this->state           = self::STATE_OPEN;
        $this->lastFailureTime = time();
    }

    /**
     * Handle successful operation
     *
     * @return void
     */
    private function onSuccess(): void
    {
        if ($this->isHalfOpen()) {
            $this->halfOpenSuccessCount++;
            if ($this->halfOpenSuccessCount >= $this->halfOpenSuccessThreshold) {
                $this->transitionToClosed();
            }
        } elseif ($this->isClosed()) {
            $this->failureCount = 0;
        }
    }

    /**
     * Handle failed operation
     *
     * @return void
     */
    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->failureCount >= $this->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    /**
     * Check if circuit should attempt reset
     *
     * @return bool True if should attempt reset.
     */
    private function shouldAttemptReset(): bool
    {
        if ($this->lastFailureTime === null) {
            return false;
        }

        return (time() - $this->lastFailureTime) >= $this->recoveryTimeout;
    }

    /**
     * Transition to closed state
     *
     * @return void
     */
    private function transitionToClosed(): void
    {
        $this->state                = self::STATE_CLOSED;
        $this->failureCount         = 0;
        $this->halfOpenSuccessCount = 0;
        $this->lastFailureTime      = null;
    }

    /**
     * Transition to open state
     *
     * @return void
     */
    private function transitionToOpen(): void
    {
        $this->state                = self::STATE_OPEN;
        $this->halfOpenSuccessCount = 0;
    }

    /**
     * Transition to half-open state
     *
     * @return void
     */
    private function transitionToHalfOpen(): void
    {
        $this->state                = self::STATE_HALF_OPEN;
        $this->halfOpenSuccessCount = 0;

        if ($this->halfOpenCallback !== null) {
            ($this->halfOpenCallback)();
        }
    }

    /**
     * Get circuit breaker statistics
     *
     * @return array<string,mixed> Statistics array.
     */
    public function getStatistics(): array
    {
        return [
            'state'                     => $this->state,
            'failure_count'             => $this->failureCount,
            'failure_threshold'         => $this->failureThreshold,
            'recovery_timeout'          => $this->recoveryTimeout,
            'last_failure_time'         => $this->lastFailureTime,
            'half_open_success_count'   => $this->halfOpenSuccessCount,
            'half_open_success_threshold' => $this->halfOpenSuccessThreshold,
        ];
    }
}
