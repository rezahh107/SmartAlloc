<?php

namespace SmartAlloc\Infra;

use SmartAlloc\Domain\Ports\CapabilityPort;

final class WpCapabilityAdapter implements CapabilityPort
{
    public function can(string $capability, int $userId = 0): bool
    {
        return \current_user_can($capability, $userId);
    }
}
