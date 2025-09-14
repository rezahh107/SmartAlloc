<?php

namespace SmartAlloc\Domain\Service;

use SmartAlloc\Domain\Ports\DbPort;
use SmartAlloc\Domain\Ports\CapabilityPort;
use SmartAlloc\Domain\Ports\ClockPort;

final class AllocService
{
    public function __construct(
        private DbPort $db,
        private CapabilityPort $cap,
        private ClockPort $clock
    ) {
    }

    public function allocate(int $userId, int $amount): bool
    {
        if (!$this->cap->can('manage_alloc', $userId)) {
            return false;
        }

        $now      = $this->clock->now()->format('Y-m-d H:i:s');
        $affected = $this->db->execute(
            'INSERT INTO sa_allocs (user_id, amount, created_at) VALUES (%d, %d, %s)',
            array($userId, $amount, $now)
        );

        return $affected > 0;
    }
}
