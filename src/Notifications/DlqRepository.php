<?php namespace SmartAlloc\Notifications;
use SmartAlloc\Infra\DbPort;
final class DlqRepository {
    public function __construct(private DbPort $db, private string $table) {}
    /** @param array<string,mixed> $row */
    public function enqueue(array $row): void {
        $sql = "INSERT INTO {$this->table} (channel, payload, reason, created_at) VALUES (%s, %s, %s, %s)";
        $args = [
            $row['channel'] ?? 'unknown',
            wp_json_encode($row['payload'] ?? []),
            substr((string) ($row['reason'] ?? ''), 0, 255),
            gmdate('Y-m-d H:i:s'),
        ];
        $this->db->exec($sql, $args);
    }
}
