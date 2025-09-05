<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Tests\Mocks;

class MockWpdb {
    private array $results = [];

    public function get_var($query = null, $x = 0, $y = 0) {
        return $this->results[$query] ?? null;
    }

    public function prepare(string $query, ...$args): string {
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    }

    public function get_results($query, $output = OBJECT) {
        return $this->results[$query] ?? [];
    }

    public function insert($table, $data, $format = null) {
        return 1;
    }
}

