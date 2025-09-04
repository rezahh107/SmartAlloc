<?php
class WpdbMock {
    public string $prefix = 'wp_';
    public int $insert_id = 1;
    public array $records = [];

    public function prepare(string $query, ...$args): string {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        return vsprintf($query, $args);
    }

    public function insert($table, $data, $format = null) {
        $this->records[] = $data;
        return $this->insert_id;
    }

    public function get_var($sql) {
        return 0;
    }
}
