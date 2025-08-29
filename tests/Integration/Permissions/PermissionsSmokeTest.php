<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PermissionsSmokeTest extends TestCase
{
    public function test_rest_export_requires_manage_cap(): void { $this->markTestIncomplete('Create user without cap, call REST endpoint, expect 403'); }
    public function test_admin_screen_blocks_unauthorized(): void { $this->markTestIncomplete('Access admin page without cap, expect wp_die() call'); }
}

