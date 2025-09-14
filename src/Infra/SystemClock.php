<?php

namespace SmartAlloc\Infra;

use SmartAlloc\Domain\Ports\ClockPort;

final class SystemClock implements ClockPort
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
