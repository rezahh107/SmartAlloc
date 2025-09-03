<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\{NotificationService,CircuitBreaker,Logging,Metrics};

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) { return json_encode($data); }
}
if (!function_exists('get_transient')) {
    function get_transient($k) { global $transients; return $transients[$k] ?? false; }
}
if (!function_exists('set_transient')) {
    function set_transient($k, $v, $e) { global $transients; $transients[$k] = $v; return true; }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback) { $GLOBALS['filters'][$hook] = $callback; return true; }
}
if (!function_exists('remove_all_filters')) {
    function remove_all_filters($hook) { unset($GLOBALS['filters'][$hook]); }
}

final class NotificationThrottleTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        global $wpdb, $filters, $t, $o;
        $t = [];
        $o = [];
        $filters = [];
        if (!defined('SMARTALLOC_NOTIFY_LIMIT_PER_MIN')) {
            define('SMARTALLOC_NOTIFY_LIMIT_PER_MIN', 2);
        }
        $wpdb = new class {
            public string $prefix = 'wp_';
            public array $inserted = [];
            public function insert(string $table, array $data): void { $this->inserted[] = $data; }
            public function query(string $sql): void {}
            public function get_results($q, $o = OBJECT) { return []; }
            public function prepare(string $q, ...$args): string { if (isset($args[0]) && is_array($args[0])) $args = $args[0]; return vsprintf($q, $args); }
            public function get_var($q) { return 0; }
        };
        \Patchwork\replace('as_enqueue_single_action', fn() => null);
        \Patchwork\replace('as_enqueue_async_action', fn() => null);
        \Patchwork\replace('wp_schedule_single_event', fn() => null);
    }

    public function testThrottledNotificationsIncrementFailedMetric(): void
    {
        global $wpdb;
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new Metrics());

        $results = [];
        for ($i = 0; $i < 4; $i++) {
            try {
                $svc->send([
                    'event_name' => 'user_registered',
                    'body' => ['user_id' => $i],
                    'recipient' => 'u@example.com',
                ]);
                $results[] = true;
            } catch (Throwable $e) {
                $results[] = false;
            }
        }

        $this->assertTrue($results[0]);
        $this->assertTrue($results[1]);
        $this->assertFalse($results[2]);
        $this->assertFalse($results[3]);

        $counts = ['notify_failed_total'=>0,'notify_throttled_total'=>0,'dlq_push_total'=>0];
        foreach ($wpdb->inserted as $row) {
            if (isset($row['metric_key']) && isset($counts[$row['metric_key']])) {
                $counts[$row['metric_key']] += (int) $row['value'];
            }
        }
        $this->assertSame(2, $counts['notify_failed_total']);
        $this->assertSame(2, $counts['notify_throttled_total']);
        $this->assertSame(2, $counts['dlq_push_total']);

        // no filters to clean up
    }

    public function testFailedMetricConsistency(): void
    {
        global $wpdb, $filters;
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new Metrics());

        $this->assertNull($svc->send(['event_name' => 'invalid']));

        set_transient('smartalloc_notify_throttle_' . md5('a'), 2, 60);
        try {
            $svc->send(['event_name' => 'user_registered', 'body' => ['user_id' => 1], 'recipient' => 'a']);
        } catch (Throwable $e) {
        }

        $filters['smartalloc_notify_transport'] = fn($r, $body, $attempt) => false;
        $svc->handle(['event_name' => 'password_reset', 'body' => ['email' => 'x@example.com']]);
        remove_all_filters('smartalloc_notify_transport');

        $counts = 0;
        foreach ($wpdb->inserted as $row) {
            if (isset($row['metric_key']) && $row['metric_key'] === 'notify_failed_total') {
                $counts += (int) $row['value'];
            }
        }
        $this->assertGreaterThanOrEqual(3, $counts);
    }
}
