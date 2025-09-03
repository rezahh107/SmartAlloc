<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\{NotificationService,CircuitBreaker,Logging,DlqService,Metrics};
use SmartAlloc\Tests\TestDoubles\SpyDlq;
use SmartAlloc\Services\Exceptions\ThrottleException;

if (!function_exists('add_action')) { function add_action($h,$c,$p=10,$a=1){} }
if (!function_exists('apply_filters')) { function apply_filters($t,$v){ return $v; } }
if (!function_exists('get_transient')) { function get_transient($k){ global $t; return $t[$k] ?? false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$e){ global $t; $t[$k]=$v; } }
if (!function_exists('as_enqueue_single_action')) { function as_enqueue_single_action($ts,$h,$a,$g,$u){ global $s; $s=[$ts,$h,$a]; } }
if (!function_exists('as_enqueue_async_action')) { function as_enqueue_async_action($h,$a,$g,$u){ global $s; $s=[0,$h,$a]; } }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event($ts,$h,$a){ global $s; $s=[$ts,$h,$a]; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($d){ return json_encode($d); } }
if (!function_exists('get_option')) { function get_option($k,$d=[]){ global $o; return $o[$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k,$v){ global $o; $o[$k]=$v; } }

final class NotificationFlowTest extends BaseTestCase
{
    public function test_flow_success_rate_limit_and_retry(): void
    {
        global $t,$s,$o; $t=$o=[]; $s=null;
        $spyDlq = new SpyDlq();
        $metrics = new class extends Metrics {
            public array $counters = [];
            public function __construct() {}
            public function inc(string $key, float $value = 1.0, array $labels = []): void { $this->counters[$key] = ($this->counters[$key] ?? 0) + $value; }
            public function observe(string $key, int $milliseconds, array $labels = []): void {}
        };
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), $metrics, null, new DlqService($spyDlq));

        // successful notification
        $svc->handle(['event_name' => 'user_registered', 'body' => ['user_id' => 1]]);
        $this->assertSame(1, $metrics->counters['notify_success_total'] ?? 0);

        // rate limit triggers DLQ
        $GLOBALS['filters']['smartalloc_notify_burst'] = fn($v)=>1;
        try {
            $svc->send(['event_name' => 'user_registered', 'body' => ['user_id' => 1], 'recipient' => 'r']);
            $svc->send(['event_name' => 'user_registered', 'body' => ['user_id' => 1], 'recipient' => 'r']);
        } catch (ThrottleException $e) {}
        $this->assertTrue($spyDlq->has('notify'));
        $this->assertSame(1, $metrics->counters['notify_throttled_total'] ?? 0);
        $this->assertSame(1, $metrics->counters['dlq_push_total'] ?? 0);
        unset($GLOBALS['filters']['smartalloc_notify_burst']);

        // failed transport retries with backoff
        $GLOBALS['filters']['smartalloc_notify_transport'] = fn() => 'fail';
        $svc->handle(['event_name' => 'password_reset', 'body' => ['email' => 'a@example.com'], '_attempt' => 1]);
        $this->assertGreaterThan(0, $s[0]);
        $this->assertSame('smartalloc_notify', $s[1]);
        $this->assertSame(1, $metrics->counters['notify_failed_total'] ?? 0);
        $this->assertSame(1, $metrics->counters['notify_retry_total'] ?? 0);
        unset($GLOBALS['filters']['smartalloc_notify_transport']);
    }
}
