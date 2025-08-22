<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Testing\TestFilters;
use SmartAlloc\Services\Db;

if (!class_exists('wpdb')) {
    class wpdb {}
}

final class DbOutageTest extends TestCase
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
            public $prefix = 'wp_';
            public $last_error = '';
            public function prepare($q, ...$a) { return preg_replace('/%[dsf]/', 'x', $q); }
            public function get_results($q, $o = ARRAY_A) { return []; }
            public function query($q) { return true; }
        };
        $db = new Db();
        $this->expectException(RuntimeException::class);
        $db->query('SELECT 1');
    }
}
