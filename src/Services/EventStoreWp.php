<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Contracts\EventStoreInterface;

/**
 * WordPress-specific event store implementation
 */
final class EventStoreWp implements EventStoreInterface
{
    private string $eventTable;
    private string $listenerTable;

    public function __construct()
    {
        global $wpdb;
        $this->eventTable = $wpdb->prefix . 'salloc_event_log';
        $this->listenerTable = $wpdb->prefix . 'salloc_event_listener_log';
    }

    /**
     * Insert an event if it doesn't already exist (deduplication)
     */
    public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int
    {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->eventTable}(event_name, dedup_key, payload_json)
             VALUES (%s, %s, %s)
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
            $event,
            $dedupeKey,
            wp_json_encode($payload)
        ));
        
        if ($result === false) {
            throw new \RuntimeException('Database insertEvent error: ' . $wpdb->last_error);
        }
        
        // If rows_affected is 2, it means it was a duplicate (UPDATE clause executed)
        return ($wpdb->rows_affected === 2) ? 0 : (int) $wpdb->insert_id;
    }

    /**
     * Start a listener run
     */
    public function startListenerRun(int $eventLogId, string $listener): int
    {
        global $wpdb;
        
        // @security-ok-sql
        $result = $wpdb->insert($this->listenerTable, [
            'event_log_id' => $eventLogId,
            'listener' => $listener,
            'status' => 'started'
        ]);
        
        if ($result === false) {
            throw new \RuntimeException('Database listener start error: ' . $wpdb->last_error);
        }
        
        return (int) $wpdb->insert_id;
    }

    /**
     * Finish a listener run
     */
    public function finishListenerRun(int $listenerRunId, string $status, ?string $error): void
    {
        global $wpdb;
        
        // @security-ok-sql
        $wpdb->update($this->listenerTable, [
            'status' => $status,
            'error_text' => $error,
            'duration_ms' => null
        ], ['id' => $listenerRunId]);
    }

    /**
     * Finish an event
     */
    public function finishEvent(int $eventLogId, string $status, ?string $error, int $durationMs): void
    {
        global $wpdb;
        
        // @security-ok-sql
        $wpdb->update($this->eventTable, [
            'status' => $status,
            'error_text' => $error,
            'duration_ms' => $durationMs,
            'finished_at' => current_time('mysql')
        ], ['id' => $eventLogId]);
    }
} 