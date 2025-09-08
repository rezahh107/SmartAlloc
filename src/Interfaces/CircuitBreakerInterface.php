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
use SmartAlloc\Services\CircuitBreakerStatus;
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
     * Reset circuit breaker to closed state.
     */
    public function reset(): void;

    /**
     * Retrieve detailed circuit breaker status
     *
     * @return CircuitBreakerStatus Status value object.
     */
    public function getStatus(): CircuitBreakerStatus;

    /**
     * Record a failure with optional error message
     *
     * @param string $error Error message.
     *
     * @return void
     */
    public function recordFailure(string $error): void;

    /**
     * Record a successful operation
     *
     * @return void
     */
    public function recordSuccess(): void;

    /**
     * Guard an operation and throw when circuit is open
     *
     * @param string $context Context identifier for the operation.
     *
     * @return void
     */
    public function guard(string $context): void;

    /**
     * Mark an operation as successful
     *
     * @param string $context Context identifier for the operation.
     *
     * @return void
     */
    public function success(string $context): void;

    /**
     * Mark an operation as failed
     *
     * @param string    $context   Context identifier for the operation.
     * @param Throwable $exception Exception that occurred.
     *
     * @return void
     */
    public function failure(string $context, Throwable $exception): void;
}
