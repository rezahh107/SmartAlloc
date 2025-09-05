<?php

declare(strict_types=1);

namespace SmartAlloc\ValueObjects;

/**
 * Circuit Breaker Status DTO
 *
 * Immutable data transfer object containing circuit breaker state information.
 */
final class CircuitBreakerStatus
{
    public readonly string $state;
    public readonly int $failCount;
    public readonly int $threshold;
    public readonly ?int $cooldownUntil;
    public readonly ?string $lastError;

    public function __construct(
        string $state,
        int $failCount,
        int $threshold,
        ?int $cooldownUntil,
        ?string $lastError
    ) {
        $this->state         = $state;
        $this->failCount     = $failCount;
        $this->threshold     = $threshold;
        $this->cooldownUntil = $cooldownUntil;
        $this->lastError     = $lastError;
    }

    /**
     * Check if circuit is in open state.
     */
    public function isOpen(): bool
    {
        return $this->state === 'open';
    }

    /**
     * Check if circuit is in closed state.
     */
    public function isClosed(): bool
    {
        return $this->state === 'closed';
    }

    /**
     * Check if circuit is in half-open state.
     */
    public function isHalfOpen(): bool
    {
        return $this->state === 'half-open';
    }

    /**
     * Convert DTO to associative array.
     */
    public function toArray(): array
    {
        return [
            'state'          => $this->state,
            'fail_count'     => $this->failCount,
            'threshold'      => $this->threshold,
            'cooldown_until' => $this->cooldownUntil,
            'last_error'     => $this->lastError,
        ];
    }
}
