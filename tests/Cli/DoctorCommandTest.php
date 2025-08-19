<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Cli\DoctorCommand;
use SmartAlloc\Tests\BaseTestCase;

final class DoctorCommandTest extends BaseTestCase
{
    private $oldWpdb;
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $this->oldWpdb = $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function prepare($q, $v) { return $q; }
            public function get_var($sql) {
                if (str_starts_with($sql, 'SHOW TABLES')) { return 'wp_smartalloc_allocations'; }
                if (str_starts_with($sql, 'SHOW INDEX')) { return 'mentor_id'; }
                return null;
            }
        };
        Functions\when('__')->returnArg();
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->oldWpdb;
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_all_checks_pass(): void
    {
        Functions\expect('wp_next_scheduled')->andReturn(time() + 3600);
        Functions\expect('rest_get_server')->andReturn(new class {
            public function get_routes(): array { return ['/smartalloc/v1/health' => []]; }
        });
        update_option('smartalloc_settings', []);

        $cmd = new DoctorCommand();
        $results = $cmd->runChecks();
        foreach ($results as $check) {
            $this->assertTrue($check['status']);
        }
    }
}
