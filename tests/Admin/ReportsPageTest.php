<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Admin\Pages\ReportsPage;

final class ReportsPageTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_html')->alias(fn($v) => $v);
        Functions\when('esc_attr')->alias(fn($v) => $v);
        Functions\when('esc_url')->alias(fn($v) => $v);
        Functions\when('admin_url')->justReturn('/admin-post.php');
        Functions\when('submit_button')->alias(fn($v) => $v);
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public array $results = [];
            public function prepare($sql, $values = []) { return $sql; }
            public function get_results($sql, $output) { return array_shift($this->results); }
        };
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_requires_capability(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(false);
        Functions\expect('wp_die')->once()->andThrow(new \RuntimeException('die'));
        $this->expectException(\RuntimeException::class);
        ReportsPage::render();
    }

    public function test_renders_filters_and_table(): void
    {
        Functions\expect('current_user_can')->andReturn(true);
        global $wpdb;
        $wpdb->results = [[[
            'grp' => '2025-01-01',
            'auto_count' => 2,
            'manual_count' => 1,
            'reject_count' => 0,
            'fuzzy_auto' => 0,
            'fuzzy_manual' => 0,
            'assigned' => 0,
            'capacity' => 0,
        ]]];
        Functions\when('wp_nonce_url')->alias(fn($u) => $u);

        ob_start();
        ReportsPage::render();
        $html = ob_get_clean();

        $this->assertStringContainsString('name="date_from"', $html);
        $this->assertStringContainsString('Export CSV', $html);
        $this->assertStringContainsString('2025-01-01', $html);
    }

    public function test_csv_download_nonce_and_headers(): void
    {
        Functions\expect('current_user_can')->andReturn(true);
        Functions\expect('check_admin_referer')->once()->with('smartalloc_reports_csv', 'smartalloc_reports_nonce');
        global $wpdb;
        $wpdb->results = [[]];
        ob_start();
        ReportsPage::downloadCsv();
        $csv = ob_get_clean();
        $this->assertStringContainsString('key,allocated,manual,reject', $csv);
    }
}
