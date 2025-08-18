<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Admin\Pages\SettingsPage;
use SmartAlloc\Tests\BaseTestCase;

final class SettingsPageTest extends BaseTestCase
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
        SettingsPage::render();
    }

    public function test_renders_fields_and_nonces(): void
    {
        $GLOBALS['sa_options'] = ['smartalloc_settings' => []];
        Functions\expect('current_user_can')->andReturn(true);
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_html')->alias(fn($v) => $v);
        Functions\when('esc_attr')->alias(fn($v) => $v);
        Functions\when('__')->alias(fn($v) => $v);
        Functions\when('esc_url')->alias(fn($v) => $v);
        Functions\expect('settings_fields')->once()->with('smartalloc_settings');
        Functions\when('admin_url')->justReturn('/options.php');
        Functions\when('submit_button')->alias(fn() => '');
        Functions\when('selected')->alias(fn($a, $b) => $a === $b ? 'selected' : '');

        $level = ob_get_level();
        ob_start();
        SettingsPage::render();
        $html = ob_get_clean();
        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        $this->assertStringContainsString('name="smartalloc_settings[fuzzy_auto_threshold]"', $html);
        $this->assertStringContainsString('name="smartalloc_settings[fuzzy_manual_min]"', $html);
        $this->assertStringContainsString('name="smartalloc_settings[fuzzy_manual_max]"', $html);
        $this->assertStringContainsString('name="smartalloc_settings[default_capacity]"', $html);
        $this->assertStringContainsString('name="smartalloc_settings[allocation_mode]"', $html);
        $this->assertStringContainsString('name="smartalloc_settings[postal_code_alias]"', $html);
        $this->assertStringContainsString('name="smartalloc_settings[export_retention_days]"', $html);
    }
}

