<?php
namespace SmartAlloc\Infra;
interface DbPort {
    public function exec(string $sql, array $args = []): int;
}
final class WpdbAdapter implements DbPort {
    public function __construct(private \wpdb $wpdb) {}

    public function exec(string $sql, array $args = []): int {
        $prepared = \SmartAlloc\Services\DbSafe::mustPrepare($sql, $args);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query($prepared);
        return (int) $this->wpdb->rows_affected;
    }
}
interface CircuitStorage {
    public function get(string $key): array;
    public function put(string $key, array $state, int $ttl): void;
    public function clear(string $key): void;
}
final class TransientCircuitStorage implements CircuitStorage {
    private string $prefix = 'smartalloc_cb_';
    public function get(string $key): array {
        $v = get_transient($this->prefix . $key);
        return is_array($v) ? $v : [];
    }
    public function put(string $key, array $state, int $ttl): void {
        set_transient($this->prefix . $key, $state, $ttl);
    }
    public function clear(string $key): void {
        delete_transient($this->prefix . $key);
    }
}
