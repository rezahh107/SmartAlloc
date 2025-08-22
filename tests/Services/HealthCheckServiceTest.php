<?php

declare(strict_types=1);

use SmartAlloc\Services\HealthCheckService;
use SmartAlloc\Tests\BaseTestCase;

final class HealthCheckServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_run_flags_issues_and_good_status(): void
    {
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function get_var($sql){
                if (str_contains($sql, 'salloc_dlq')) return 1; // backlog
                if (str_contains($sql, 'salloc_mentors')) return 2; // over capacity
                return 1; // db ok
            }
        };
        wp_cache_flush();
        $svc = new HealthCheckService();
        $res = $svc->run();
        $this->assertSame('critical', $res['status']);

        $wpdb = new class {
            public $prefix = 'wp_';
            public function get_var($sql){ return 0; }
        };
        wp_cache_flush();
        wp_cache_set('__sa_health__', '1', 'smartalloc', 1);
        $res2 = $svc->run();
        $this->assertSame('good', $res2['status']);
    }
}
