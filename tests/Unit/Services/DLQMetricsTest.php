<?php
// phpcs:ignoreFile
declare(strict_types=1);

use SmartAlloc\Tests\Unit\TestCase as BaseTestCase;
use SmartAlloc\Services\DLQMetrics;
use Brain\Monkey\Functions;

final class DLQMetricsTest extends BaseTestCase
{
    public function test_records_metrics_and_event(): void
    {
        global $o, $actions;
        /** @var array<string, mixed> $o */
        $o = $actions = [];

        Functions\when('get_option')->alias(
            function (string $k, $d = false) use (&$o): mixed {
                return $o[$k] ?? $d;
            }
        );

        Functions\when('update_option')->alias(
            function (string $k, $v) use (&$o): bool {
                $o[$k] = $v;
                return true;
            }
        );

        $m = new DLQMetrics();
        $m->recordPush('q1', []);
        $m->recordFinalFailure('q1', []);
        $m1 = get_option('smartalloc_dlq_metrics');
        $this->assertSame(1, $m1['q1']['pushes']);
        $this->assertSame(1, $m1['q1_failures']['pushes']);
    }
}
