<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Dead letter queue storage service.
 */
final class DlqService
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'salloc_dlq';
    }

    /**
     * Push a payload to DLQ.
     *
     * @param array<string,mixed> $payload
     */
    public function push(string $event, array $payload, string $error, int $attempts): void
    {
        global $wpdb;
        $wpdb->insert($this->table, [
            'event_name'    => $event,
            'payload_json'  => wp_json_encode($payload),
            'error_text'    => $error,
            'attempts'      => $attempts,
            'status'        => 'ready',
            'created_at_utc'=> gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * List last N DLQ items.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(int $limit = 200): array
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT id,event_name,payload_json,error_text,attempts,created_at_utc FROM {$this->table} WHERE status=%s ORDER BY id DESC LIMIT %d",
            'ready',
            $limit
        );
        // @security-ok-sql
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        foreach ($rows as &$r) {
            $r['payload'] = json_decode((string) $r['payload_json'], true);
            unset($r['payload_json']);
        }
        unset($r);
        return $rows;
    }

    /**
     * Get single DLQ item.
     *
     * @return array<string,mixed>|null
     */
    public function get(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d AND status=%s", $id, 'ready'),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        $row['payload'] = json_decode((string) $row['payload_json'], true);
        unset($row['payload_json']);
        return $row;
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => $id]);
    }

    /**
     * Retry a DLQ item.
     */
    public function retry(int $id): bool
    {
        $row = $this->get($id);
        if (!$row || !is_array($row['payload'])) {
            return false;
        }
        do_action('smartalloc_notify', ['payload' => $row['payload'], '_attempt' => 1]);
        $this->delete($id);
        return true;
    }
}
