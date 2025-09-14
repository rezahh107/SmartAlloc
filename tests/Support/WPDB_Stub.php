<?php

namespace SmartAlloc\Tests\Support;

class WPDB_Stub
{
    public array $last_call = [];

    public function __construct($dbuser = '', $dbpassword = '', $dbname = '', $dbhost = '')
    {
    }

    public function prepare(string $query, ...$args): string
    {
        $this->last_call = ['prepare', $query, $args];
        if (! $args) {
            return $query;
        }
        foreach ($args as $arg) {
            $query = preg_replace('/%s/', "'{$arg}'", $query, 1);
        }
        return $query;
    }

    public function get_var(string $query)
    {
        $this->last_call = ['get_var', $query];
        return null;
    }

    public function get_results(string $query): array
    {
        $this->last_call = ['get_results', $query];
        return [];
    }

    public function query(string $query)
    {
        $this->last_call = ['query', $query];
        return 0;
    }

    public function __call(string $name, array $args)
    {
        $this->last_call = [$name, $args];
        return null;
    }
}

\class_alias(__NAMESPACE__ . '\\WPDB_Stub', 'wpdb');
