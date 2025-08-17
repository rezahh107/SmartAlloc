<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Atomic counter service
 */
final class CounterService
{
    public function __construct(
        private Db $db,
        private Logging $logger
    ) {}

    /**
     * Get next counter value atomically
     */
    public function next(string $scope): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'salloc_counters';
        
        $aff = $this->db->exec(
            "INSERT INTO {$table}(scope, val) VALUES(%s, 1) 
             ON DUPLICATE KEY UPDATE val = LAST_INSERT_ID(val + 1)",
            [$scope]
        );
        
        $id = (int) $wpdb->insert_id;
        if ($id <= 0) {
            throw new \RuntimeException('Counter failed');
        }
        
        return $id;
    }
} 