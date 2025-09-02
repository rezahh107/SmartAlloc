<?php

declare(strict_types=1);

namespace SmartAlloc\ValueObjects;

use DateTimeImmutable;

final class CircuitBreakerStatus
{
    public function __construct(
        public readonly bool $isOpen,
        public readonly int $failureCount,
        public readonly ?DateTimeImmutable $openedAt,
        public readonly ?DateTimeImmutable $nextAttempt,
        public readonly string $serviceName
    ) {
    }

    public function toArray(): array
    {
        return [
            'is_open' => $this->isOpen,
            'failure_count' => $this->failureCount,
            'opened_at' => $this->openedAt?->format('Y-m-d H:i:s'),
            'next_attempt' => $this->nextAttempt?->format('Y-m-d H:i:s'),
            'service_name' => $this->serviceName,
        ];
    }
}
