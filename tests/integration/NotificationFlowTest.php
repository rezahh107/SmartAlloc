<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\{NotificationService,CircuitBreaker,Logging,Metrics,DlqService};
use SmartAlloc\Tests\TestDoubles\SpyDlq;

if (!defined('SMARTALLOC_NOTIFY_MAX_TRIES')) { define('SMARTALLOC_NOTIFY_MAX_TRIES', 3); }
if (!function_exists('add_action')) { function add_action(){} }
if (!function_exists('get_transient')) { function get_transient($k){ global $t; return $t[$k] ?? false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$e){ global $t; $t[$k] = $v; } }
if (!function_exists('apply_filters')) { function apply_filters($t,$v){ return $v; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($d){ return json_encode($d); } }
if (!function_exists('as_enqueue_single_action')) { function as_enqueue_single_action($ts,$h,$a,$g=null,$u=true){ global $s; $s=[$ts,$h,$a]; } }
if (!function_exists('as_enqueue_async_action')) { function as_enqueue_async_action($h,$a,$g=null,$u=true){ global $s; $s=[$h,$a]; } }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event($ts,$h,$a){ global $s; $s=[$ts,$h,$a]; } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($h,$a){ return false; } }
if (!function_exists('current_time')) { function current_time($t='mysql'){ return gmdate('Y-m-d H:i:s'); } }
if (!function_exists('trailingslashit')) { function trailingslashit($p){ return rtrim($p,'/').'/'; } }
if (!function_exists('wp_upload_dir')) { function wp_upload_dir(){ return ['basedir' => sys_get_temp_dir()]; } }
if (!function_exists('wp_mkdir_p')) { function wp_mkdir_p($p){ return true; } }

class SpyMetrics extends Metrics {
    public array $counters = [];
    public function __construct() {}
    public function inc(string $key, float $value = 1.0, array $labels = []): void {
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;
    }
    public function observe(string $key, int $milliseconds, array $labels = []): void {}
}

final class NotificationFlowTest extends TestCase
{
    public function test_successful_notification_updates_metrics(): void
    {
        global $t; $t = [];
        $metrics = new SpyMetrics();
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), $metrics);
        $svc->handle(['event_name' => 'ok', 'body' => []]);
        $this->assertSame(1.0, $metrics->counters['notify_success_total'] ?? 0.0);
    }

    public function test_final_failure_goes_to_dlq(): void
    {
        global $t, $s; $t = []; $s = null;
        $spy = new SpyDlq();
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new SpyMetrics(), null, new DlqService($spy));
        $GLOBALS['filters']['smartalloc_notify_transport'] = fn() => 'fail';
        $svc->handle(['event_name' => 'x', 'body' => [], '_attempt' => SMARTALLOC_NOTIFY_MAX_TRIES]);
        $this->assertTrue($spy->has('x'));
        unset($GLOBALS['filters']['smartalloc_notify_transport']);
    }

    public function test_failed_transport_retries_with_backoff(): void
    {
        global $t, $s; $t = []; $s = null;
        $spy = new SpyDlq();
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new SpyMetrics(), null, new DlqService($spy));
        $GLOBALS['filters']['smartalloc_notify_transport'] = fn() => 'fail';
        $svc->handle(['event_name' => 'y', 'body' => [], '_attempt' => 1]);
        $this->assertNotNull($s);
        $this->assertGreaterThan(0, $s[0]);
        unset($GLOBALS['filters']['smartalloc_notify_transport']);
    }
}
