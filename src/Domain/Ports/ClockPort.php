<?php

namespace SmartAlloc\Domain\Ports;

interface ClockPort
{
    public function now(): \DateTimeImmutable;
}
