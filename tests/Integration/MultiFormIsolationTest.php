<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Migrations\FormTenantMigrator;
use SmartAlloc\Tests\BaseTestCase;

final class MultiFormIsolationTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function get_charset_collate() { return ''; }
            public function query($sql) { return true; }
        };
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_provisions_tables_per_form(): void
    {
        $captured = [];
        Functions\when('dbDelta')->alias(function($sql) use (&$captured) { $captured[] = $sql; });
        FormTenantMigrator::provisionFormTenant($GLOBALS['wpdb'], 150);
        FormTenantMigrator::provisionFormTenant($GLOBALS['wpdb'], 200);
        $this->assertTrue(
            (bool) array_filter($captured, fn($s) => str_contains($s, 'smartalloc_allocations_f150'))
        );
        $this->assertTrue(
            (bool) array_filter($captured, fn($s) => str_contains($s, 'smartalloc_allocations_f200'))
        );
    }
}
