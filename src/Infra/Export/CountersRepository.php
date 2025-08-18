<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Export;

/**
 * Persistent export counters.
 */
class CountersRepository
{
    private string $table;

    public function __construct(private $wpdb = null)
    {
        if ($this->wpdb !== null) {
            $this->table = $this->wpdb->prefix . 'smartalloc_counters';
        } else {
            $this->table = 'wp_smartalloc_counters';
        }
    }

    /**
     * Retrieve next counters, incrementing daily and batch values atomically.
     *
     * @return array{int,int} [dailyCounter, batchCounter]
     */
    public function getNextCounters(): array
    {
        $lockAcquired = false;
        if ($this->wpdb !== null) {
            $lockName    = 'smartalloc_export_lock';
            $lockResult  = $this->wpdb->get_var($this->wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lockName));
            $lockAcquired = ($lockResult === '1' || $lockResult === 1);
        }

        try {
            if ($this->wpdb === null) {
                return [1, 1];
            }

            $today = gmdate('Y_m_d');
            $now   = gmdate('Y-m-d H:i:s');

            $sql = "INSERT INTO {$this->table} (id, current_date, daily_counter, batch_counter, updated_at)
                    VALUES (1, %s, 1, 1, %s)
                    ON DUPLICATE KEY UPDATE
                        daily_counter = IF(current_date = VALUES(current_date), daily_counter + 1, 1),
                        current_date = VALUES(current_date),
                        batch_counter = batch_counter + 1,
                        updated_at = VALUES(updated_at)";
            $prepared = $this->wpdb->prepare($sql, $today, $now);
            $this->wpdb->query($prepared);

            $row = $this->wpdb->get_row("SELECT daily_counter, batch_counter FROM {$this->table} WHERE id = 1", ARRAY_A);
            $daily = isset($row['daily_counter']) ? (int) $row['daily_counter'] : 0;
            $batch = isset($row['batch_counter']) ? (int) $row['batch_counter'] : 0;
            return [$daily, $batch];
        } finally {
            if ($lockAcquired && $this->wpdb !== null) {
                $lockName = 'smartalloc_export_lock';
                $this->wpdb->get_var($this->wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
            }
        }
    }
}
