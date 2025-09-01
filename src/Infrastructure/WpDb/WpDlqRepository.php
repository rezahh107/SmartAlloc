<?php

declare(strict_types=1);

namespace SmartAlloc\Infrastructure\WpDb;

use SmartAlloc\Infrastructure\Contracts\DlqRepository;
use DateTimeImmutable;
use DateTimeZone;

final class WpDlqRepository implements DlqRepository {
    public function __construct(private WpdbAdapter $db, private string $table) {}
    public function insert(string $topic, array $payload, DateTimeImmutable $createdAtUtc): void {
        $this->db->insertPrepared($this->table, [
            'topic' => $topic,
            'payload' => wp_json_encode($payload),
            'created_at' => $createdAtUtc->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }
}
