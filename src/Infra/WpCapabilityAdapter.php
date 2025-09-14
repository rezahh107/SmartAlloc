<?php

namespace SmartAlloc\Infra;

use SmartAlloc\Domain\Ports\CapabilityPort;

class WpCapabilityAdapter implements CapabilityPort
{
    public function currentUserCan(string $capability): bool
    {
        if (! function_exists('current_user_can')) {
            return false;
        }
        return \current_user_can($capability);
    }
}
