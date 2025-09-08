<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Tests\Helpers;

class TestWpdb extends \wpdb
{
    public string $prefix = 'wp_';
    public string $last_query = '';
    public int $insert_id = 1;
    /** @var list<object> */
    public array $results = [];
    private bool $returned = false;
    public function __construct()
    {
    }
    public function query($sql): void
    {
        $this->last_query = (string) $sql;
    }
    public function get_results($sql, $output = OBJECT): array
    {
        $this->last_query = (string) $sql;
        if ($this->returned) {
            return [];
        }
        $this->returned = true;
        return $this->results;
    }
    public function prepare($sql, ...$args): string
    {
        $args = is_array($args[0] ?? null) ? $args[0] : $args;
        $this->last_query = vsprintf($sql, $args);
        return $this->last_query;
    }
}
