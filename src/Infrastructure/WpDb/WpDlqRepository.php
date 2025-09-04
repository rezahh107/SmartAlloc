<?php

declare(strict_types=1);

namespace SmartAlloc\Infrastructure\WpDb;

use SmartAlloc\Infrastructure\Contracts\{DbProxy, DlqRepository};
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class WpDlqRepository implements DlqRepository
{
    public function __construct(private DbProxy $db, private string $table) {}

    public static function createDefault(): self
    {
        $db = WpdbAdapter::fromGlobals();
        return new self($db, $db->getPrefix() . 'smartalloc_dlq');
    }

    /**
     * Insert a new DLQ record into the database.
     *
     * @param string              $topic       Topic name.
     * @param array<string,mixed> $payload     Payload data.
     * @param DateTimeImmutable   $createdAtUtc Creation time in UTC.
     *
     * @return bool True on success, false on failure.
     *
     * @throws Throwable When database operation fails.
     */
    public function insert(string $topic, array $payload, DateTimeImmutable $createdAtUtc): bool
    {
        try {
            $this->db->insert($this->table, [
                'topic'      => $topic,
                'payload'    => wp_json_encode($payload),
                'created_at' => $createdAtUtc->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('WpDlqRepository::insert: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return false;
        }
    }

    /**
     * Retrieve recent DLQ records.
     *
     * @param int $limit Maximum number of records to retrieve.
     *
     * @return array<int,array<string,mixed>> Records list, empty on failure.
     *
     * @throws Throwable When database query fails.
     */
    public function listRecent(int $limit): array
    {
        try {
            $sql  = "SELECT id,topic AS event_name,payload,attempts,error_text,created_at FROM {$this->table} ORDER BY created_at DESC LIMIT %d";
            $rows = $this->db->getResults($sql, [$limit]);
            foreach ($rows as &$r) {
                $r['payload'] = json_decode((string) $r['payload'], true);
            }
            unset($r);
            return $rows;
        } catch (Throwable $e) {
            error_log('WpDlqRepository::listRecent: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return [];
        }
    }

    /**
     * Retrieve a DLQ record by ID.
     *
     * @param int $id Record identifier.
     *
     * @return array<string,mixed>|null Record data or null on failure.
     *
     * @throws Throwable When database query fails.
     */
    public function get(int $id): ?array
    {
        try {
            $sql = "SELECT id,topic AS event_name,payload,attempts,error_text,created_at FROM {$this->table} WHERE id=%d";
            $row = $this->db->getRow($sql, [$id]);
            if ($row) {
                $row['payload'] = json_decode((string) $row['payload'], true);
            }
            return $row;
        } catch (Throwable $e) {
            error_log('WpDlqRepository::get: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return null;
        }
    }

    /**
     * Delete a DLQ record.
     *
     * @param int $id Record identifier.
     *
     * @return bool True on success, false on failure.
     *
     * @throws Throwable When database operation fails.
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->delete($this->table, ['id' => $id]);
            return true;
        } catch (Throwable $e) {
            error_log('WpDlqRepository::delete: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return false;
        }
    }

    /**
     * Count DLQ records.
     *
     * @return int Number of records, 0 on error.
     *
     * @throws Throwable When database query fails.
     */
    public function count(): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table}";
            return (int) ($this->db->getVar($sql) ?? 0);
        } catch (Throwable $e) {
            error_log('WpDlqRepository::count: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return 0;
        }
    }
}

