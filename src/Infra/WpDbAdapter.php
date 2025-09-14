<?php

namespace SmartAlloc\Infra;

use SmartAlloc\Domain\Ports\DbPort;

final class WpDbAdapter implements DbPort
{
    public function __construct(private \wpdb $wpdb)
    {
    }

    public function fetchOne(string $sql, array $params = array()): mixed
    {
        $prepared = $this->wpdb->prepare($sql, ...$params);
        return $this->wpdb->get_var($prepared);
    }

    public function fetchAll(string $sql, array $params = array()): array
    {
        $prepared = $this->wpdb->prepare($sql, ...$params);
        $rows     = $this->wpdb->get_results($prepared, 'ARRAY_A');
        return $rows ? $rows : array();
    }

    public function execute(string $sql, array $params = array()): int
    {
        $prepared = $this->wpdb->prepare($sql, ...$params);
        $this->wpdb->query($prepared);

        return (int) $this->wpdb->rows_affected;
    }
}
