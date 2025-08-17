<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Statistics service for daily metrics
 */
final class StatsService
{
    public function __construct(
        private Db $db,
        private Logging $logger
    ) {}

    /**
     * Rebuild daily statistics
     */
    public function rebuildDaily(): void
    {
        global $wpdb;
        $statsTable = $wpdb->prefix . 'salloc_stats_daily';
        $mentorsTable = $wpdb->prefix . 'salloc_mentors';
        
        // Clear today's stats
        $this->db->exec("DELETE FROM {$statsTable} WHERE day = CURDATE()");
        
        // Aggregate from mentors table
        $rows = $this->db->query("
            SELECT 
                manager_id, 
                center_id, 
                gender, 
                'ALL' AS group_code, 
                SUM(capacity) as cap, 
                SUM(assigned) as ass 
            FROM {$mentorsTable} 
            GROUP BY manager_id, center_id, gender
        ");
        
        foreach ($rows as $row) {
            $this->db->insert($statsTable, [
                'stat_date' => gmdate('Y-m-d'),
                'manager_id' => (int) $row['manager_id'],
                'center_id' => (int) $row['center_id'],
                'gender' => $row['gender'],
                'group_code' => $row['group_code'],
                'capacity' => (int) $row['cap'],
                'assigned' => (int) $row['ass']
            ]);
        }
        
        $this->logger->info('stats.rebuilt', ['date' => gmdate('Y-m-d')]);
    }
} 