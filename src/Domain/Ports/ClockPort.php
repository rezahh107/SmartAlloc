<?php

namespace SmartAlloc\Domain\Ports;

use DateTimeImmutable;

interface ClockPort
{
    public function now(): DateTimeImmutable;
}
