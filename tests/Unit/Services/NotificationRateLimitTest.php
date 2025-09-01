<?php
declare(strict_types=1);

use SmartAlloc\Services\{NotificationService,CircuitBreaker,Logging,Metrics};
use PHPUnit\Framework\TestCase;

if (!function_exists('add_action')) { function add_action() {} }
if (!function_exists('apply_filters')) { function apply_filters($t, $v) { return $v; } }
if (!function_exists('get_transient')) {
    function get_transient($k) { global $t; return $t[$k] ?? false; }
}
if (!function_exists('set_transient')) {
    function set_transient($k, $v, $e) { global $t; $t[$k] = $v; }
}
if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($ts, $hook, $args) {}
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($d) { return json_encode($d); }
}
if (!function_exists('trailingslashit')) {
    function trailingslashit($p) { return rtrim($p, '/') . '/'; }
}
if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = '';
        public $queries = [];
        public function insert($t, $d) {}
        public function get_results($q, $type = ARRAY_A) { return []; }
        public function get_row($q, $type = ARRAY_A) { return []; }
        public function prepare($q, ...$a) { return $q; }
    }
}

final class NotificationRateLimitTest extends TestCase
{
    public function test_rate_limiting_blocks_excessive_notifications(): void
    {
        global $t; $t = [];
        $GLOBALS['wpdb'] = new wpdb();
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new Metrics());
        for ($i = 0; $i < 10; $i++) {
            $svc->send(['body' => []]);
        }
        $this->assertSame(10, get_transient('smartalloc_notify_rate'));
        $svc->send(['body' => []]);
        $this->assertSame(10, get_transient('smartalloc_notify_rate'));
    }

    public function test_rate_limit_resets_after_transient_cleared(): void
    {
        global $t; $t = ['smartalloc_notify_rate' => 10];
        $GLOBALS['wpdb'] = new wpdb();
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new Metrics());
        $svc->send(['body' => []]);
        $this->assertSame(10, get_transient('smartalloc_notify_rate'));
        unset($t['smartalloc_notify_rate']);
        $svc->send(['body' => []]);
        $this->assertSame(1, get_transient('smartalloc_notify_rate'));
    }
}
