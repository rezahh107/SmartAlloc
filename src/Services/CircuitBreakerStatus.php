<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Data transfer object representing circuit breaker status.
 */
final class CircuitBreakerStatus
{
    private const ALLOWED_STATES = ['open', 'closed', 'half-open'];

    public readonly string $state;
    public readonly int $failCount;
    public readonly int $threshold;
    public readonly ?int $cooldownUntil;
    public readonly ?string $lastError;

    /**
     * @throws \InvalidArgumentException When state is invalid.
     */
    public function __construct(
        string $state,
        int $failCount,
        int $threshold,
        ?int $cooldownUntil = null,
        ?string $lastError = null
    ) {
        if (!in_array($state, self::ALLOWED_STATES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid state: %s', $state));
        }

        $this->state = $state;
        $this->failCount = $failCount;
        $this->threshold = $threshold;
        $this->cooldownUntil = $cooldownUntil;
        $this->lastError = $lastError !== null ? substr($lastError, 0, 100) : null;
    }

    public function isOpen(): bool
    {
        return $this->state === 'open';
    }

    public function isClosed(): bool
    {
        return $this->state === 'closed';
    }

    public function isHalfOpen(): bool
    {
        return $this->state === 'half-open';
    }

    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'fail_count' => $this->failCount,
            'threshold' => $this->threshold,
            'cooldown_until' => $this->cooldownUntil,
            'last_error' => $this->lastError,
        ];
    }
}
