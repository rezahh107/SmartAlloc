<?php
declare(strict_types=1);

if (!class_exists('wpdb', false)) {
    class wpdb {
        public string $prefix = 'wp_';
        /** @var array<int,string> */
        public array $queries = [];
        /** @var array<int,array<string,mixed>> */
        public array $rows = [];
        /** @var array<int,array<string,mixed>> */
        public array $mentors = [];
        /** @var array<int,array<string,mixed>> */
        public array $results = [];
        /** @var array<int,array<string,mixed>> */
        public array $history = [];
        public int $var = 0;
        public string $last_error = '';
        public int $rows_affected = 0;
        public int $insert_id = 0;

        public function __construct()
        {
            $this->mentors = [
                1 => ['mentor_id' => 1, 'gender' => 'M', 'center' => '1', 'group_code' => 'EX', 'capacity' => 3, 'assigned' => 0, 'active' => 1],
                2 => ['mentor_id' => 2, 'gender' => 'F', 'center' => '1', 'group_code' => 'MA', 'capacity' => 3, 'assigned' => 0, 'active' => 1],
            ];
        }

        private function log(string $sql): void
        {
            $this->queries[] = $sql;
        }

        public function prepare(string $query, ...$args): string
        {
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }
            foreach ($args as &$a) {
                $a = is_numeric($a) ? (int)$a : $a;
            }
            $query = str_replace(['%d','%s','%f'], ['%u','%s','%F'], $query);
            return vsprintf($query, $args);
        }

        public function query(string $sql)
        {
            $this->log($sql);
            if (stripos($sql, 'START TRANSACTION') !== false || stripos($sql, 'COMMIT') !== false || stripos($sql, 'ROLLBACK') !== false) {
                return 1;
            }
            if (preg_match('/UPDATE wp_salloc_mentors SET assigned = assigned \+ 1 WHERE mentor_id = (\d+)/', $sql, $m)) {
                $id = (int)$m[1];
                $mentor = $this->mentors[$id] ?? ['assigned' => 0, 'capacity' => 0];
                if ($mentor['assigned'] < $mentor['capacity']) {
                    $mentor['assigned']++;
                    $this->mentors[$id] = $mentor;
                    $this->rows_affected = 1;
                } else {
                    $this->rows_affected = 0;
                }
                return 1;
            }
            if (preg_match("/UPDATE wp_smartalloc_allocations SET status = '([^']+)'/i", $sql, $m)) {
                if (preg_match('/WHERE entry_id = (\d+)/', $sql, $m2)) {
                    $id = (int)$m2[1];
                    if (!isset($this->rows[$id])) {
                        $this->rows_affected = 0;
                        return 0;
                    }
                    $status = $m[1];
                    $this->rows[$id]['status'] = $status;
                    if (preg_match('/mentor_id = (\d+)/', $sql, $m3)) {
                        $this->rows[$id]['mentor_id'] = (int)$m3[1];
                    }
                    if (preg_match("/reason_code = '([^']+)'/", $sql, $m4)) {
                        $this->rows[$id]['reason_code'] = $m4[1];
                    }
                    $this->rows_affected = 1;
                    return 1;
                }
            }
            return 0;
        }

        public function get_row(string $sql, $output = ARRAY_A)
        {
            $this->log($sql);
            if (preg_match('/entry_id = (\d+)/', $sql, $m)) {
                $id = (int)$m[1];
                return $this->rows[$id] ?? null;
            }
            return null;
        }

        public function get_results($sql, $output = ARRAY_A)
        {
            $this->log((string)$sql);
            if ($this->results) {
                return $this->results;
            }
            return array_values($this->mentors);
        }

        public function get_var($sql)
        {
            $this->log((string)$sql);
            return $this->var;
        }

        public function insert(string $table, array $data)
        {
            $this->log('INSERT');
            $this->insert_id++;
            if (isset($data['entry_id'])) {
                $id = $data['entry_id'];
                if (isset($this->rows[$id])) {
                    $this->last_error = 'duplicate';
                    return false;
                }
                $this->rows[$id] = $data;
                return $this->insert_id;
            }
            if (str_contains($table, 'history')) {
                $this->history[] = $data;
            }
            return $this->insert_id;
        }
    }
}
