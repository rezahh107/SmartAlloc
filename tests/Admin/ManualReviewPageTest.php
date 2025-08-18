<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Admin\Pages\ManualReviewPage;

final class ManualReviewPageTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_requires_capability(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(false);
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\expect('wp_die')->once()->andThrow(new \RuntimeException('die'));

        $this->expectException(\RuntimeException::class);
        ManualReviewPage::render();
    }

    public function test_renders_table_with_filters_and_nonces(): void
    {
        Functions\expect('current_user_can')->andReturn(true);
        Functions\when('plugins_url')->alias(fn($p, $f) => $p);
        Functions\when('wp_enqueue_script')->alias(fn() => null);
        Functions\when('wp_enqueue_style')->alias(fn() => null);
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_html')->alias(fn($v) => $v);
        Functions\when('esc_attr')->alias(fn($v) => $v);
        Functions\when('__')->alias(fn($v) => $v);
        Functions\when('submit_button')->alias(fn() => '');
        Functions\when('wp_nonce_field')->alias(fn() => '');

        $repo = new class {
            public function findManualPage($page, $perPage, $filters) {
                return ['rows' => [['entry_id' => 1, 'status' => 'manual']], 'total' => 1];
            }
        };
        $GLOBALS['smartalloc_repo'] = $repo;
        global $wpdb;
        $wpdb = new \WpdbStub();
        $wpdb->results = [['entry_id'=>1,'status'=>'manual','mentor_id'=>null,'candidates'=>null]];
        $wpdb->var = 1;

        ob_start();
        ManualReviewPage::render();
        $html = ob_get_clean();

        $this->assertStringContainsString('name="reason_code"', $html);
        $this->assertStringContainsString('<table', $html);
    }
}
