<?php

namespace SmartAlloc\Infra;

use SmartAlloc\Domain\Ports\ClockPort;
use DateTimeImmutable;
use DateTimeZone;

class SystemClock implements ClockPort
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
