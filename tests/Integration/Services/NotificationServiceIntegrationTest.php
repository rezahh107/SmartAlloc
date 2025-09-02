<?php
declare(strict_types=1);

use SmartAlloc\Services\{NotificationService,CircuitBreaker,Logging,Metrics};
use SmartAlloc\Http\Rest\HealthController;
use SmartAlloc\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

if (!function_exists('add_action')) { function add_action(){} }
if (!function_exists('get_transient')) { function get_transient($k){ global $t; return $t[$k] ?? false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$e){ global $t; $t[$k] = $v; } }
if (!function_exists('get_option')) { function get_option($k,$d=false){ global $o; return $o[$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k,$v){ global $o; $o[$k] = $v; } }
if (!function_exists('wp_cache_set')) { function wp_cache_set($k,$v,$g,$t){} }
if (!function_exists('wp_cache_get')) { function wp_cache_get($k,$g){ return 1; } }
if (!function_exists('current_user_can')) { function current_user_can($c){ return true; } }
if (!function_exists('apply_filters')) { function apply_filters($t,$v){ return $v; } }
if (!defined('SMARTALLOC_CAP')) { define('SMARTALLOC_CAP', 'manage_options'); }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }
if (!class_exists('WP_REST_Request')) { class WP_REST_Request {} }
if (!class_exists('WP_REST_Response')) { class WP_REST_Response { public function __construct(private array $d=[], private int $s=200){} public function get_data(){ return $this->d; } } }
if (!class_exists('WP_Error')) { class WP_Error { public function __construct(public string $c='', public string $m='', public array $d=[]){} } }

final class NotificationServiceIntegrationTest extends TestCase
{
    public function test_throttle_stats_exposed_in_health(): void
    {
        global $t, $o; $t = $o = [];
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), new Metrics());
        for ($i = 0; $i < 11; $i++) {
            if ($i === 3) { global $t; $t['smartalloc_notify_rate'] = 0; }
            try { $svc->send(['recipient' => 'r', 'body' => []]); } catch (\Throwable $e) {}
        }
        $ctrl = new HealthController(new RateLimiter());
        $data = $ctrl->handle(new WP_REST_Request())->get_data();
        $stats = get_option('smartalloc_throttle_stats');
        $this->assertGreaterThan(0, $stats['hits']);
        $this->assertArrayHasKey('dlq', $data['metrics']);
    }
}
