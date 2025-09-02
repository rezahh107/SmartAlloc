<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\DLQMetrics;

if (!function_exists('get_option')) { function get_option($k,$d=false){ global $o; return $o[$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k,$v){ global $o; $o[$k]=$v; } }

final class DLQMetricsTest extends TestCase
{
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
