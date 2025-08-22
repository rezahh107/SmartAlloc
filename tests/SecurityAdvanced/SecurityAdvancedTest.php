<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\SecurityAdvanced;

use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;
use SmartAlloc\Security\RateLimiter;
use SmartAlloc\Logging\Logger;
use SmartAlloc\Infra\Metrics\MetricsCollector;
use SmartAlloc\Http\Rest\MetricsController;
use WP_Error;
use WP_REST_Request;

class SecurityAdvancedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
        if (!defined('SMARTALLOC_TEST_MODE')) {
            define('SMARTALLOC_TEST_MODE', true);
        }
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_rate_limiting_per_endpoint_and_user(): void
    {
        delete_transient('smartalloc_rl_' . md5('rest:metrics:1'));
        delete_transient('smartalloc_rl_' . md5('rest:metrics:2'));
        $rl = new RateLimiter([
            'metrics' => ['limit' => 2, 'window' => 60],
            'health' => ['limit' => 1, 'window' => 60],
            'dlq' => ['limit' => 1, 'window' => 60],
            'dlq_retry' => ['limit' => 1, 'window' => 60],
        ]);
        $this->assertNull($rl->enforce('metrics', 1));
        $this->assertNull($rl->enforce('metrics', 1));
        $err = $rl->enforce('metrics', 1);
        $this->assertInstanceOf(WP_Error::class, $err);
        $data = $err->get_error_data();
        $this->assertSame(429, $data['status']);
        $this->assertArrayHasKey('retry_after', $data);
        // different user unaffected
        $this->assertNull($rl->enforce('metrics', 2));
    }

    public function test_rest_routes_require_manage_capability(): void
    {
        $collector = new MetricsCollector();
        $rl = new RateLimiter(['metrics'=>['limit'=>5,'window'=>60]]);
        $controller = new MetricsController($collector, $rl);
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('current_user_can')->alias(fn() => false);
        $resp = $controller->handle(new WP_REST_Request());
        $this->assertInstanceOf(WP_Error::class, $resp);
        $this->assertSame(403, $resp->get_error_data()['status']);

        Functions\when('current_user_can')->alias(fn($cap) => $cap === SMARTALLOC_CAP);
        $resp2 = $controller->handle(new WP_REST_Request());
        $this->assertInstanceOf(\WP_REST_Response::class, $resp2);
    }

    public function test_no_sensitive_data_in_logs_and_errors(): void
    {
        $logger = new Logger(Logger::DEBUG);
        $logger->info('user', [
            'email' => 'foo@example.com',
            'mobile' => '1234567890',
            'national_id' => '9999',
        ]);
        $record = $logger->records[0]['context'];
        $this->assertStringNotContainsString('foo@example.com', $record['email'] ?? '');
        $this->assertStringNotContainsString('1234567890', $record['mobile'] ?? '');
        $this->assertStringNotContainsString('9999', $record['national_id'] ?? '');

        delete_transient('smartalloc_rl_' . md5('rest:metrics:1'));
        $rl = new RateLimiter(['metrics'=>['limit'=>1,'window'=>60]]);
        $rl->enforce('metrics', 1);
        $err = $rl->enforce('metrics', 1);
        $this->assertInstanceOf(WP_Error::class, $err);
        $msg = method_exists($err, 'get_error_message') ? $err->get_error_message() : '';
        $this->assertStringNotContainsString('SELECT', $msg);
    }

    public function test_nonce_and_session_rules(): void
    {
        $this->markTestSkipped('no nonce/session paths');
    }
}
