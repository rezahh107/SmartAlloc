<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Testing\TestFilters;
use SmartAlloc\Services\Db;


final class DbOutageTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        TestFilters::reset();
        parent::tearDown();
    }

    public function testDbOutageThrows(): void
    {
        TestFilters::set(['db_outage' => true]);
        global $wpdb;
        $wpdb = new class extends wpdb {
            public string $prefix = 'wp_';
            public string $last_error = '';
            public function prepare(string $q, ...$a): string { return preg_replace('/%[dsf]/', 'x', $q); }
            public function get_results($q, $o = ARRAY_A) { return []; }
            public function query($q) { return true; }
        };
        $db = new Db();
        $this->expectException(RuntimeException::class);
        $db->query('SELECT 1');
    }
}
