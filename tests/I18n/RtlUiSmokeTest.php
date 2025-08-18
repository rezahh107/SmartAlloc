<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Admin\Pages\SettingsPage;
use SmartAlloc\Tests\BaseTestCase;

final class RtlUiSmokeTest extends BaseTestCase
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

    public function test_settings_page_renders_under_rtl(): void
    {
        $GLOBALS['sa_options'] = ['smartalloc_settings' => []];
        Functions\expect('current_user_can')->andReturn(true);
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_html')->alias(fn($v) => $v);
        Functions\when('esc_attr')->alias(fn($v) => $v);
        Functions\when('esc_url')->alias(fn($v) => $v);
        Functions\when('__')->alias(fn($v) => $v);
        Functions\when('settings_fields')->alias(fn() => '');
        Functions\when('admin_url')->justReturn('/options.php');
        Functions\when('submit_button')->alias(fn() => '');
        Functions\when('selected')->alias(fn($a, $b) => $a === $b ? 'selected' : '');
        Functions\when('checked')->alias(fn($a, $b) => $a === $b ? 'checked' : '');
        Functions\expect('is_rtl')->andReturn(true);

        $level = ob_get_level();
        ob_start();
        SettingsPage::render();
        $html = ob_get_clean();
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        $this->assertNotEmpty($html);
    }
}
