<?php

namespace SmartAlloc\Infra;

use SmartAlloc\Domain\Ports\DbPort;
use wpdb;

class WpDbAdapter implements DbPort
{
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function prepare(string $query, ...$args): string
    {
        return $this->wpdb->prepare($query, ...$args);
    }

    public function getVar(string $query)
    {
        return $this->wpdb->get_var($query);
    }

    public function getResults(string $query): array
    {
        $results = $this->wpdb->get_results($query);
        return is_array($results) ? $results : [];
    }

    public function query(string $query)
    {
        return $this->wpdb->query($query);
    }
}
