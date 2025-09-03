<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\{NotificationService,CircuitBreaker,Logging,DlqService};
use SmartAlloc\Tests\TestDoubles\{SpyDlq,NullMetrics};

class SpyMetrics extends SmartAlloc\Services\Metrics
{
    public array $counters = [];
    public function __construct() {}
    public function inc(string $key, float $value = 1.0, array $labels = []): void
    {
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;
    }
    public function observe(string $key, int $milliseconds, array $labels = []): void {}
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('SMARTALLOC_NOTIFY_MAX_TRIES')) {
    define('SMARTALLOC_NOTIFY_MAX_TRIES', 3);
}
if (!defined('SMARTALLOC_NOTIFY_BASE_DELAY')) {
    define('SMARTALLOC_NOTIFY_BASE_DELAY', 5);
}
if (!defined('SMARTALLOC_NOTIFY_BACKOFF_CAP')) {
    define('SMARTALLOC_NOTIFY_BACKOFF_CAP', 600);
}
if (!function_exists('add_action')) {
    function add_action() {}
}
if (!function_exists('wp_mail')) {
    function wp_mail() {
        global $mail_ok;
        return $mail_ok;
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($d) {
        return json_encode($d);
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($t, $v) {
        return $v;
    }
}
if (!function_exists('get_option')) {
    function get_option($k, $d = null) {
        return $d;
    }
}
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return ['basedir' => '/tmp'];
    }
}
if (!function_exists('trailingslashit')) {
    function trailingslashit($p) {
        return rtrim($p, '/') . '/';
    }
}
if (!function_exists('as_enqueue_single_action')) {
    function as_enqueue_single_action($ts, $h, $a, $g, $u) {
        global $s;
        $s = [$ts, $h, $a];
    }
}
if (!function_exists('as_enqueue_async_action')) {
    function as_enqueue_async_action($h, $a, $g, $u) {
        global $s;
        $s = [$h, $a];
    }
}
if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($ts, $h, $a) {
        global $s;
        $s = [$ts, $h, $a];
    }
}
if (!function_exists('get_transient')) {
    function get_transient($k) {
        global $t;
        return $t[$k] ?? false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($k, $v, $e) {
        global $t;
        $t[$k] = $v;
    }
}
if (!function_exists('current_time')) {
    function current_time($t = 'mysql') {
        return gmdate('Y-m-d H:i:s');
    }
}

final class NotificationServiceTest extends BaseTestCase
{
    public function test_final_email_failure_goes_to_dlq(): void
    {
        global $mail_ok, $s, $t;
        $mail_ok = false;
        $s = null;
        $t = [];
        $spy = new SpyDlq();
        $dlq = new DlqService($spy);
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new NullMetrics(), null, $dlq);
        $svc->sendMail(['to' => 'a', 'subject' => 's', 'message' => 'm', '_attempt' => SMARTALLOC_NOTIFY_MAX_TRIES]);
        $this->assertTrue($spy->has('mail'));
    }

    public function test_dlq_payload_includes_all_metadata(): void
    {
        global $mail_ok, $s, $t;
        $mail_ok = false;
        $s = null;
        $t = [];
        $spy = new SpyDlq();
        $dlq = new DlqService($spy);
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new NullMetrics(), null, $dlq);
        $svc->sendMail(['to' => 'a', 'subject' => 's', 'message' => 'm', '_attempt' => SMARTALLOC_NOTIFY_MAX_TRIES, 'meta' => 'x']);
        $p = $spy->last('mail');
        $this->assertSame('x', $p['meta']);
        $this->assertSame(SMARTALLOC_NOTIFY_MAX_TRIES, $p['attempts']);
    }

    public function test_retry_limit_uses_config_constant(): void
    {
        global $mail_ok, $s, $t;
        $mail_ok = false;
        $s = null;
        $t = [];
        $spy = new SpyDlq();
        $dlq = new DlqService($spy);
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new NullMetrics(), null, $dlq);
        $GLOBALS['filters']['smartalloc_notify_transport'] = fn() => 'fail';
        $svc->handle(['event_name' => 'mail', 'body' => [], '_attempt' => SMARTALLOC_NOTIFY_MAX_TRIES]);
        $this->assertTrue($spy->has('mail'));
        unset($GLOBALS['filters']['smartalloc_notify_transport']);
    }

    public function test_throttle_respects_dynamic_filters(): void
    {
        global $t;
        $t = [];
        $GLOBALS['filters']['smartalloc_notify_burst'] = fn($v) => 2;
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new NullMetrics());
        $svc->send(['event_name' => 'a']);
        $svc->send(['event_name' => 'b']);
        $svc->send(['event_name' => 'c']);
        $this->assertSame(2, $t['smartalloc_notify_rate'] ?? 0);
        unset($GLOBALS['filters']['smartalloc_notify_burst']);
    }

    public function test_dlq_after_retries_with_metrics(): void
    {
        global $s, $t;
        $s = null;
        $t = [];
        $spy = new SpyDlq();
        $dlq = new DlqService($spy);
        $metrics = new SpyMetrics();
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), $metrics, null, $dlq);
        $GLOBALS['filters']['smartalloc_notify_transport'] = fn() => 'fail';
        $svc->handle(['event_name' => 'x', 'body' => [], '_attempt' => SMARTALLOC_NOTIFY_MAX_TRIES]);
        $this->assertTrue($spy->has('x'));
        $this->assertSame(1.0, $metrics->counters['notify_failed_total'] ?? 0.0);
        $this->assertSame(1.0, $metrics->counters['dlq_push_total'] ?? 0.0);
        unset($GLOBALS['filters']['smartalloc_notify_transport']);
    }
}

