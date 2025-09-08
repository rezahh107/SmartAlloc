<?php

/**
 * Circuit Breaker Interface
 *
 * @package SmartAlloc
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SmartAlloc\Interfaces;

use SmartAlloc\Exceptions\CircuitBreakerException;
use SmartAlloc\ValueObjects\CircuitBreakerStatus;
use Throwable;

/**
 * Circuit Breaker Interface
 *
 * Defines the contract for circuit breaker implementations.
 */
interface CircuitBreakerInterface
{
    /**
     * Execute operation with circuit breaker protection
     *
     * @param callable $operation Operation to execute.
     * @param mixed    ...$args   Operation arguments.
     *
     * @return mixed Operation result.
     * @throws CircuitBreakerException When circuit is open.
     */
    public function execute(callable $operation, ...$args);

    /**
     * Check if circuit breaker is open
     *
     * @return bool True if circuit is open.
     */
    public function isOpen(): bool;

    /**
     * Check if circuit breaker is closed
     *
     * @return bool True if circuit is closed.
     */
    public function isClosed(): bool;

    /**
     * Get current circuit state
     *
     * @return string Current state.
     */
    public function getState(): string;

    /**
     * Check circuit breaker before executing an operation.
     *
     * @param string $key Operation identifier.
     *
     * @throws CircuitBreakerException When circuit is open.
     */
    public function guard(string $key): void;

    /**
     * Record a successful operation.
     *
     * @param string $key Operation identifier.
     */
    public function success(string $key): void;

    /**
     * Record a failed operation.
     *
     * @param string    $key       Operation identifier.
     * @param Throwable $exception Failure exception.
     */
    public function failure(string $key, Throwable $exception): void;

    /**
     * Retrieve circuit breaker status information.
     *
     * @return CircuitBreakerStatus Status snapshot.
     */
    public function getStatus(): CircuitBreakerStatus;

    /**
     * Reset circuit breaker to closed state
     *
     * @return void
     */
    public function reset(): void;
}
