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
        $this->eventTable   = $wpdb->prefix . 'salloc_event_log';
        $this->listenerTable = $wpdb->prefix . 'salloc_event_listener_log';
    }

    public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int
    {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$this->eventTable}(event_name, dedup_key, payload_json, status, created_at)
                 VALUES (%s, %s, %s, 'started', %s)
                 ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
                $event,
                $dedupeKey,
                $this->encode($payload),
                gmdate('Y-m-d H:i:s')
            )
        );

        if ($result === false) {
            throw new \RuntimeException('Database insertEvent error: ' . $wpdb->last_error);
        }

        return ($wpdb->rows_affected === 2) ? 0 : (int) $wpdb->insert_id;
    }

    public function startListenerRun(int $eventLogId, string $listener): int
    {
        global $wpdb;

        $result = $wpdb->insert($this->listenerTable, [
            'event_log_id' => $eventLogId,
            'listener'     => $listener,
            'status'       => 'started',
            'started_at'   => gmdate('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            throw new \RuntimeException('Database listener start error: ' . $wpdb->last_error);
        }

        return (int) $wpdb->insert_id;
    }

    public function finishListenerRun(int $listenerRunId, string $status, ?string $error, int $durationMs): void
    {
        global $wpdb;

        $wpdb->update(
            $this->listenerTable,
            [
                'status'      => $status,
                'error_text'  => $error,
                'duration_ms' => $durationMs,
                'finished_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $listenerRunId]
        );
    }

    /**
     * Encode payload deterministically.
     *
     * @param array<string,mixed> $payload
     */
    private function encode(array $payload): string
    {
        ksort($payload);
        foreach ($payload as &$v) {
            if (is_array($v)) {
                $v = $this->sortRecursive($v);
            }
        }
        unset($v);
        return wp_json_encode($payload);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function sortRecursive(array $data): array
    {
        ksort($data);
        foreach ($data as &$v) {
            if (is_array($v)) {
                $v = $this->sortRecursive($v);
            }
        }
        unset($v);
        return $data;
    }

    public function finishEvent(int $eventLogId, string $status, ?string $error, int $durationMs): void
    {
        global $wpdb;

        $wpdb->update(
            $this->eventTable,
            [
                'status'      => $status,
                'error_text'  => $error,
                'duration_ms' => $durationMs,
                'finished_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $eventLogId]
        );
    }
}
