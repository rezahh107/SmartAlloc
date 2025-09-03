<?php

declare(strict_types=1);

namespace SmartAlloc\Infrastructure\WpDb;

use SmartAlloc\Infrastructure\Contracts\{DbProxy, DlqRepository};
use DateTimeImmutable;
use DateTimeZone;

final class WpDlqRepository implements DlqRepository
{
    public function __construct(private DbProxy $db, private string $table) {}

    public static function createDefault(): self
    {
        $db = WpdbAdapter::fromGlobals();
        return new self($db, $db->getPrefix() . 'smartalloc_dlq');
    }

    public function insert(string $topic, array $payload, DateTimeImmutable $createdAtUtc): void
    {
        $this->db->insert($this->table, [
            'topic'      => $topic,
            'payload'    => wp_json_encode($payload),
            'created_at' => $createdAtUtc->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function listRecent(int $limit): array
    {
        $sql = "SELECT id,topic AS event_name,payload,attempts,error_text,created_at FROM {$this->table} ORDER BY created_at DESC LIMIT %d";
        $rows = $this->db->getResults($sql, [$limit]);
        foreach ($rows as &$r) {
            $r['payload'] = json_decode((string) $r['payload'], true);
        }
        unset($r);
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function get(int $id): ?array
    {
        $sql = "SELECT id,topic AS event_name,payload,attempts,error_text,created_at FROM {$this->table} WHERE id=%d";
        $row = $this->db->getRow($sql, [$id]);
        if ($row) {
            $row['payload'] = json_decode((string) $row['payload'], true);
        }
        return $row;
    }

    public function delete(int $id): void
    {
        $this->db->delete($this->table, ['id' => $id]);
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        return (int) ($this->db->getVar($sql) ?? 0);
    }
}

