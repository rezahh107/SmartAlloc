<?php

declare(strict_types=1);

namespace SmartAlloc\Infrastructure\WpDb;

final class WpdbAdapter {
    public function __construct(private \wpdb $wpdb) {}
    /**
     * @param array<string,mixed> $data
     */
    public function insertPrepared(string $table, array $data): void {
        $this->wpdb->insert($table, $data);
    }
}
