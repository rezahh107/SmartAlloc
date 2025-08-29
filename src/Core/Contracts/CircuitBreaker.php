<?php
declare(strict_types=1);

namespace SmartAlloc\Core\Contracts;

/**
 * Circuit breaker pattern for protecting against cascading failures.
 *
 * Lightweight contract to be injected later; no runtime usage yet.
 */
interface CircuitBreaker {
    /**
     * Check if circuit is open (failing) for given key.
     *
     * @param string $key Service or operation identifier
     * @return bool True if circuit is open (should not proceed)
     */
    public function isOpen(string $key): bool;

    /**
     * Record successful operation.
     *
     * @param string $key Service or operation identifier
     */
    public function recordSuccess(string $key): void;

    /**
     * Record failed operation.
     *
     * @param string $key Service or operation identifier
     */
    public function recordFailure(string $key): void;
}
