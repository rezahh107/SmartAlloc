<?php

declare(strict_types=1);

namespace {
    if (!class_exists('wpdb')) {
        class wpdb
        {
            public string $prefix = 'wp_';
            public array $replaced = [];
            public array $updated = [];
            public function get_charset_collate() { return ''; }
            public function replace($table, $data) { $this->replaced[] = ['table'=>$table,'data'=>$data]; }
            public function update($table, $data, $where) { $this->updated[] = ['table'=>$table,'data'=>$data,'where'=>$where]; }
            public function get_results($sql, $output = OBJECT) { return []; }
            public function query($sql) { return true; }
        }
    }
}

namespace SmartAlloc\Tests\Admin {

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Admin\FormsScreen;

final class FormsScreenTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new \wpdb();
        Functions\when('dbDelta')->justReturn(true);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_handle_enable_inserts_registry_record(): void
    {
        $_POST = ['form_id' => '150'];
        Functions\expect('check_admin_referer')->once()->with('smartalloc_enable_form');
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\expect('wp_unslash')->andReturn('150');
        Functions\expect('admin_url')->andReturn('/admin.php');
        Functions\expect('wp_safe_redirect')->once()->with('/admin.php?page=smartalloc_forms&enabled=150')->andThrow(new \RuntimeException('redirect'));

        try {
            FormsScreen::handleEnable();
        } catch (\RuntimeException $e) {
            $this->assertSame('redirect', $e->getMessage());
        }

        $this->assertSame('wp_smartalloc_forms', $GLOBALS['wpdb']->replaced[0]['table']);
        $this->assertSame(150, $GLOBALS['wpdb']->replaced[0]['data']['form_id']);
    }

    public function test_handle_disable_updates_status(): void
    {
        $_POST = ['form_id' => '150'];
        Functions\expect('check_admin_referer')->once()->with('smartalloc_disable_form');
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\expect('wp_unslash')->andReturn('150');
        Functions\expect('admin_url')->andReturn('/admin.php');
        Functions\expect('wp_safe_redirect')->once()->with('/admin.php?page=smartalloc_forms&disabled=150')->andThrow(new \RuntimeException('redirect'));

        try {
            FormsScreen::handleDisable();
        } catch (\RuntimeException $e) {
            $this->assertSame('redirect', $e->getMessage());
        }

        $this->assertSame('wp_smartalloc_forms', $GLOBALS['wpdb']->updated[0]['table']);
        $this->assertSame('disabled', $GLOBALS['wpdb']->updated[0]['data']['status']);
    }
}

}
