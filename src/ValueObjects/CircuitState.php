<?php
declare(strict_types=1);

namespace SmartAlloc\ValueObjects;

use DateTimeImmutable;

enum CircuitStatus: string
{
    case OPEN = 'open';
    case HALF = 'half';
    case CLOSED = 'closed';
}

final class CircuitState
{
    public function __construct(
        private CircuitStatus $status,
        private int $failureCount,
        private ?DateTimeImmutable $lastFailureTime,
        private array $errorSamples = []
    ) {}

    public function status(): CircuitStatus
    {
        return $this->status;
    }

    public function failureCount(): int
    {
        return $this->failureCount;
    }

    public function lastFailureTime(): ?DateTimeImmutable
    {
        return $this->lastFailureTime;
    }

    /** @return array<int,string> */
    public function errorSamples(): array
    {
        return $this->errorSamples;
    }

    /** @return array{status:string,failure_count:int,last_failure_time:?string,error_samples:array<int,string>} */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'failure_count' => $this->failureCount,
            'last_failure_time' => $this->lastFailureTime?->format('Y-m-d H:i:s'),
            'error_samples' => $this->errorSamples,
        ];
    }
}

