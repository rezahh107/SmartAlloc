<?php

declare(strict_types=1);

namespace SmartAlloc\Services\Exceptions;

final class CircuitOpenException extends \RuntimeException
{
    private string $circuitKey;
    private int $failureCount;
    private int $cooldownUntil;

    public function __construct(
        string $circuitKey,
        int $failureCount,
        int $cooldownUntil,
        string $message = 'Circuit breaker is open'
    ) {
        parent::__construct($message);
        $this->circuitKey = $circuitKey;
        $this->failureCount = $failureCount;
        $this->cooldownUntil = $cooldownUntil;
    }

    public function getCircuitKey(): string
    {
        return $this->circuitKey;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function getCooldownUntil(): int
    {
        return $this->cooldownUntil;
    }
}
