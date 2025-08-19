<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ReportsLogs;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Admin\Pages\ReportsPage;
use SmartAlloc\Tests\BaseTestCase;

final class AdminReportTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Monkey::class)) {
            self::markTestSkipped('Brain Monkey not installed');
        }
        Monkey\setUp();
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_html')->alias(fn($v) => $v);
        Functions\when('esc_attr')->alias(fn($v) => $v);
        Functions\when('esc_url')->alias(fn($v) => $v);
        Functions\when('admin_url')->justReturn('/admin-post.php');
        Functions\when('submit_button')->alias(fn($v) => $v);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_nonce_url')->alias(fn($u) => $u);

        $metrics = [
            'rows' => [[
                'date' => '2025-01-01',
                'allocated' => 1,
                'manual' => 0,
                'reject' => 0,
                'fuzzy_auto_rate' => 0,
                'fuzzy_manual_rate' => 0,
                'capacity_used' => 0,
                'mobile' => '09123456789',
                'national_id' => '12345678',
                'postal_code' => '12345',
            ]],
            'total' => [
                'allocated' => 1,
                'manual' => 0,
                'reject' => 0,
                'fuzzy_auto_rate' => 0,
                'fuzzy_manual_rate' => 0,
                'capacity_used' => 0,
            ],
        ];
        Functions\when('apply_filters')->alias(function ($hook, $value) use ($metrics) {
            if ($hook === 'smartalloc_reports_metrics') {
                return $metrics;
            }
            return $value;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_render_has_metrics_but_no_pii(): void
    {
        ob_start();
        ReportsPage::render();
        $html = ob_get_clean();

        $this->assertStringContainsString('Allocated', $html);
        $this->assertStringNotContainsString('09123456789', $html);
        $this->assertStringNotContainsString('12345678', $html);
        $this->assertStringNotContainsString('12345', $html);
    }
}
