<?php
// phpcs:ignoreFile
declare(strict_types=1);

use SmartAlloc\Tests\Unit\TestCase as BaseTestCase;
use SmartAlloc\Services\DLQMetrics;
use Brain\Monkey\Functions;

final class DLQMetricsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('SMARTALLOC_CAP')) {
            define('SMARTALLOC_CAP', 'manage_smartalloc');
        }

        Functions\when('get_option')->alias(function ($k, $d = false) { global $o; return $o[$k] ?? $d; });
        Functions\when('update_option')->alias(function ($k, $v) { global $o; $o[$k] = $v; });
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_records_metrics_and_event(): void
    {
        global $o, $actions; $o = $actions = [];
        $m = new DLQMetrics();
        $m->recordPush('q1', []);
        $m->recordFinalFailure('q1', []);
        $m1 = get_option('smartalloc_dlq_metrics');
        $this->assertSame(1, $m1['q1']['pushes']);
        $this->assertSame(1, $m1['q1_failures']['pushes']);
    }
}
