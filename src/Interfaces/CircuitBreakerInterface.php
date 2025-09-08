<?php

/**
 * Circuit Breaker Interface
 *
 * @package SmartAlloc\Interfaces
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SmartAlloc\Interfaces;

use SmartAlloc\Exceptions\CircuitBreakerException;

/**
 * Defines contract for circuit breaker implementations.
 */
interface CircuitBreakerInterface
{
    /**
     * Execute operation with protection.
     *
     * @param callable $operation Operation.
     * @param mixed    ...$args  Arguments.
     * @return mixed
     * @throws CircuitBreakerException When circuit is open.
     */
    public function execute(callable $operation, ...$args);

    /**
     * Is circuit open?
     */
    public function isOpen(): bool;

    /**
     * Is circuit closed?
     */
    public function isClosed(): bool;

    /**
     * Get state.
     */
    public function getState(): string;

    /**
     * Reset circuit.
     */
    public function reset(): void;
}
