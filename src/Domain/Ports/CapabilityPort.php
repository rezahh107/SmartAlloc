<?php

namespace SmartAlloc\Domain\Ports;

interface CapabilityPort
{
    public function currentUserCan(string $capability): bool;
}
