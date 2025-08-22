<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Services\DbSafe;

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
     * Push an entry to the DLQ.
     *
     * @param array<string,mixed> $entry
     */
    public function push(array $entry): void
    {
        global $wpdb;

        $payload = (array) ($entry['payload'] ?? []);
        $payloadJson = $this->encode($payload);

        $wpdb->query('START TRANSACTION');
        $wpdb->insert($this->table, [
            'event_name' => (string) ($entry['event_name'] ?? ''),
            'payload'    => $payloadJson,
            'attempts'   => (int) ($entry['attempts'] ?? 0),
            'error_text' => (string) ($entry['error_text'] ?? ''),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $wpdb->query('COMMIT');
    }

    /**
     * List recent DLQ items.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listRecent(int $limit = 200): array
    {
        global $wpdb;
        $sql = DbSafe::mustPrepare(
            "SELECT id,event_name,payload,attempts,error_text,created_at FROM {$this->table} ORDER BY created_at DESC LIMIT %d",
            [$limit]
        );
        // @security-ok-sql
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        foreach ($rows as &$r) {
            $r['payload'] = json_decode((string) $r['payload'], true);
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
        $sql = DbSafe::mustPrepare(
            "SELECT id,event_name,payload,attempts,error_text,created_at FROM {$this->table} WHERE id=%d",
            [$id]
        );
        $row = $wpdb->get_row($sql, ARRAY_A);
        if (!$row) {
            return null;
        }
        $row['payload'] = json_decode((string) $row['payload'], true);
        return $row;
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => $id]);
    }

    /**
     * Encode payload deterministically.
     *
     * @param array<string,mixed> $data
     */
    private function encode(array $data): string
    {
        ksort($data);
        foreach ($data as &$v) {
            if (is_array($v)) {
                $v = $this->sortRecursive($v);
            }
        }
        unset($v);
        return wp_json_encode($data);
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
}
