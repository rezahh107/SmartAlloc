<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Support\UtcTimeHelper;

/**
 * Health monitoring service
 */
final class HealthService
{
    public function __construct(
        private Db $db,
        private Cache $cache
    ) {
    }

    /**
     * Get system health status
     */
    public function status(): array
    {
        $dbOk = true;
        $cacheOk = true;
        $notes = [];

        // Check database
        try {
            $this->db->query('SELECT 1');
        } catch (\Throwable $e) {
            $dbOk = false;
            $notes[] = $e->getMessage();
        }

        // Check cache
        $this->cache->l1Set('health.test', 'ok', 5);
        $cacheOk = ($this->cache->l1Get('health.test') === 'ok');

        return [
            'db' => $dbOk,
            'cache' => $cacheOk,
            'notes' => $notes,
            'time' => UtcTimeHelper::getCurrentUtcDatetime(),
            'version' => defined('SMARTALLOC_VERSION') ? SMARTALLOC_VERSION : 'dev',
        ];
    }
}
