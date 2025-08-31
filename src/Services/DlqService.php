<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Services\DbSafe;

/* phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching */

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
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
        $row = $wpdb->get_row($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

    /** @return array{ok:int,fail:int,depth:int} */
    public function replay(int $limit = 100): array
    {
        $stats = $this->doReplay($limit);

        return [
            'ok'    => (int) ($stats['ok'] ?? 0),
            'fail'  => (int) ($stats['fail'] ?? 0),
            'depth' => (int) ($stats['depth'] ?? 0),
        ];
    }

    /** @return array{ok:int,fail:int,depth:int} */
    private function doReplay(int $limit): array
    {
        $rows = $this->listRecent($limit);
        $ok = 0;
        $fail = 0;
        foreach ($rows as $row) {
            $payload = [
                'event_name' => (string) $row['event_name'],
                'body'       => $row['payload'],
                '_attempt'   => 1,
            ];
            try {
                \do_action('smartalloc_notify', $payload);
                $this->delete((int) $row['id']);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
            }
        }
        $depth = $this->count();
        return ['ok' => $ok, 'fail' => $fail, 'depth' => $depth];
    }

    private function count(): int
    {
        global $wpdb;
        $sql = DbSafe::mustPrepare("SELECT COUNT(*) FROM {$this->table}", []);
        // @security-ok-sql
        return (int) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
