<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Admin\Pages\ExportPage;
use Mockery;

final class ExportPageTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function prepare($sql, $limit) { return $sql; }
            public function get_results($sql, $output) { return []; }
        };
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_capability_required_for_export_page(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(false);
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_url')->alias(fn($v) => $v);
        Functions\expect('wp_die')->once()->andThrow(new \RuntimeException('die'));

        $this->expectException(\RuntimeException::class);
        ExportPage::render();
    }

    public function test_form_renders_inputs_and_nonce(): void
    {
        Functions\expect('current_user_can')->andReturn(true);
        Functions\expect('wp_nonce_field')->once()->with('smartalloc_export_generate', 'smartalloc_export_nonce')->andReturn('');
        Functions\expect('wp_enqueue_script')->once()->with('smartalloc-export', Mockery::type('string'), Mockery::type('array'), SMARTALLOC_VERSION, true);
        Functions\expect('wp_enqueue_style')->once()->with('smartalloc-export', Mockery::type('string'), Mockery::type('array'), SMARTALLOC_VERSION);
        Functions\when('plugins_url')->alias(fn($p, $f) => $p);
        Functions\when('admin_url')->justReturn('/admin-post.php');
        Functions\when('esc_url')->alias(fn($v) => $v);
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_html')->alias(fn($v) => $v);
        Functions\when('size_format')->alias(fn($v) => (string) $v);
        Functions\when('wp_nonce_url')->alias(fn($v) => $v);
        Functions\when('submit_button')->alias(fn($v) => $v);

        ob_start();
        ExportPage::render();
        $html = ob_get_clean();

        $this->assertStringContainsString('name="mode"', $html);
        $this->assertStringContainsString('name="date_from"', $html);
        $this->assertStringContainsString('name="date_to"', $html);
        $this->assertStringContainsString('name="batch_id"', $html);
    }
}
