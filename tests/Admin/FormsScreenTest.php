<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Admin;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Admin\FormsScreen;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

final class FormsScreenTest extends BaseTestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public array $replaced = [];
            public function get_charset_collate() { return ''; }
            public function replace($table, $data) { $this->replaced[] = $data; return true; }
        };
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_handle_enable_provisions_tables_and_registry_row(): void {
        Functions\expect('check_admin_referer')->with('smartalloc_enable_form');
        Functions\expect('current_user_can')->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\expect('wp_unslash')->andReturn('150');
        Functions\expect('wp_safe_redirect');
        Mockery::mock('alias:SmartAlloc\Migrations\FormTenantMigrator')
            ->shouldReceive('ensureRegistryTable')->once()
            ->andReturnTrue();
        Mockery::mock('alias:SmartAlloc\Migrations\FormTenantMigrator')
            ->shouldReceive('provisionFormTenant')->once();
        $_POST['form_id'] = '150';
        FormsScreen::handleEnable();
        global $wpdb;
        $this->assertSame('enabled', $wpdb->replaced[0]['status']);
    }
}
