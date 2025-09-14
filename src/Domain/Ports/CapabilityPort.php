<?php

namespace SmartAlloc\Domain\Ports;

interface CapabilityPort
{
    public function can(string $capability, int $userId = 0): bool;
}
