<?php
declare(strict_types=1);

namespace SmartAlloc\ValueObjects;

final class ThrottleConfig
{
    public function __construct(
        private int $maxPerHour = 100,
        private int $burstLimit = 10,
        private int $windowSeconds = 3600
    ) {}

    public static function default(): self
    {
        return new self();
    }

    public function maxPerHour(): int
    {
        return $this->maxPerHour;
    }

    public function burstLimit(): int
    {
        return $this->burstLimit;
    }

    public function windowSeconds(): int
    {
        return $this->windowSeconds;
    }
}

