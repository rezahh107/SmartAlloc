<?php

/**
 * Circuit Breaker Service for SmartAlloc
 *
 * @package SmartAlloc\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Interfaces\CircuitBreakerInterface;
use SmartAlloc\Exceptions\CircuitBreakerException;
use SmartAlloc\Exceptions\CircuitBreakerCallbackException;
use Psr\Log\LoggerInterface;
use Closure;

/**
 * Circuit Breaker implementation providing fault tolerance.
 */
class CircuitBreaker implements CircuitBreakerInterface
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $state = self::STATE_CLOSED;
    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $failureCount = 0;
    private ?int $lastFailureTime = null;
    private ?Closure $halfOpenCallback;
    private int $halfOpenSuccessCount = 0;
    private int $halfOpenSuccessThreshold;
    private ?LoggerInterface $logger;
    private ?string $name;

    public function __construct(
        int $failureThreshold = 5,
        int $recoveryTimeout = 60,
        ?Closure $halfOpenCallback = null,
        int $halfOpenSuccessThreshold = 3,
        ?LoggerInterface $logger = null,
        ?string $name = null
    ) {
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
        $this->halfOpenCallback = $halfOpenCallback;
        $this->halfOpenSuccessThreshold = $halfOpenSuccessThreshold;
        $this->logger = $logger;
        $this->name = $name;
    }

    /**
     * Execute an operation guarded by the circuit breaker.
     *
     * @param callable $operation Operation to execute.
     * @param mixed    ...$args   Arguments for operation.
     * @return mixed
     * @throws CircuitBreakerException When circuit is open.
     */
    public function execute(callable $operation, ...$args)
    {
        if ($this->isOpen()) {
            if ($this->shouldAttemptReset()) {
                $this->transitionToHalfOpen();
            } else {
                throw new CircuitBreakerException('Circuit breaker is open. Operation blocked.');
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
     * Determine if circuit is open.
     */
    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    /**
     * Determine if circuit is closed.
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Determine if circuit is half-open.
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    /**
     * Get current state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get failure threshold.
     */
    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    /**
     * Reset breaker to closed state.
     */
    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->lastFailureTime = null;
        $this->halfOpenSuccessCount = 0;
    }

    /**
     * Force breaker open.
     */
    public function forceOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->lastFailureTime = time();
    }

    private function onSuccess(): void
    {
        if ($this->isHalfOpen()) {
            $this->halfOpenSuccessCount++;
            if ($this->halfOpenSuccessCount >= $this->halfOpenSuccessThreshold) {
                $this->transitionToClosed();
            }
        } else {
            $this->failureCount = 0;
        }
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();
        if ($this->failureCount >= $this->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    private function shouldAttemptReset(): bool
    {
        return $this->lastFailureTime !== null
            && (time() - $this->lastFailureTime) >= $this->recoveryTimeout;
    }

    private function transitionToClosed(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->halfOpenSuccessCount = 0;
        $this->lastFailureTime = null;
    }

    private function transitionToOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->halfOpenSuccessCount = 0;
    }

    /**
     * Transition to half-open state
     *
     * @return void
     * @throws CircuitBreakerCallbackException When callback fails.
     */
    private function transitionToHalfOpen(): void
    {
        $this->state = self::STATE_HALF_OPEN;
        $this->halfOpenSuccessCount = 0;

        if ($this->halfOpenCallback !== null) {
            try {
                if (property_exists($this, 'logger') && $this->logger !== null) {
                    $this->logger->debug(
                        'Executing half-open callback',
                        ['circuit_breaker' => $this->name ?? 'default']
                    );
                }

                ($this->halfOpenCallback)();
            } catch (\Throwable $exception) {
                if (property_exists($this, 'logger') && $this->logger !== null) {
                    $this->logger->error(
                        'Circuit breaker callback failed',
                        [
                            'circuit_breaker' => $this->name ?? 'default',
                            'exception_type' => get_class($exception),
                            'exception_message' => $exception->getMessage(),
                        ]
                    );
                }

                throw new CircuitBreakerCallbackException(
                    'Circuit breaker half-open callback failed: ' . $exception->getMessage(),
                    $exception,
                    0,
                    $exception
                );
            }
        }
    }

    /**
     * Return statistics about the breaker.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'failure_threshold' => $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
            'last_failure_time' => $this->lastFailureTime,
            'half_open_success_count' => $this->halfOpenSuccessCount,
            'half_open_success_threshold' => $this->halfOpenSuccessThreshold,
        ];
    }
}
