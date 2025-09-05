<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Services\{NotificationService, CircuitBreaker, Logging, DlqService};
use SmartAlloc\Exceptions\ThrottleException;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Tests\TestDoubles\SpyDlq;
use SmartAlloc\Tests\Helpers\SpyMetrics;
use SmartAlloc\ValueObjects\ThrottleConfig;


if (!defined('SMARTALLOC_NOTIFY_MAX_TRIES')) {
    define('SMARTALLOC_NOTIFY_MAX_TRIES', 3);
}
if (!defined('SMARTALLOC_NOTIFY_BASE_DELAY')) {
    define('SMARTALLOC_NOTIFY_BASE_DELAY', 5);
}
if (!defined('SMARTALLOC_NOTIFY_BACKOFF_CAP')) {
    define('SMARTALLOC_NOTIFY_BACKOFF_CAP', 600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

final class NotificationServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('sanitize_textarea_field')->alias(fn($v) => $v);
        Functions\when('get_transient')->alias(function ($k) { global $t; return $t[$k] ?? false; });
        Functions\when('set_transient')->alias(function ($k, $v, $e) { global $t; $t[$k] = $v; });
        Functions\when('as_enqueue_single_action')->alias(function () {});
        Functions\when('as_enqueue_async_action')->alias(function () {});
        Functions\when('wp_schedule_single_event')->alias(function () {});
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testRetryMetricIncrementsOnFailure(): void
    {
        global $t; $t = [];
        Functions\expect('wp_mail')->once()->andReturn(false);
        $metrics = new SpyMetrics();
        $dlq = new DlqService(new SpyDlq());
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), $metrics, null, $dlq);
        $res = $svc->sendMail(['to' => 'test@example.com', 'subject' => 'Test', 'message' => 'Test message']);
        $this->assertFalse($res);
        $this->assertSame(1, $metrics->counters['notify_retry_total'] ?? 0);
    }

    public function testRetryMetricNotIncrementedOnSuccess(): void
    {
        global $t; $t = [];
        Functions\expect('wp_mail')->once()->andReturn(true);
        $metrics = new SpyMetrics();
        $dlq = new DlqService(new SpyDlq());
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), $metrics, null, $dlq);
        $res = $svc->sendMail(['to' => 'test@example.com', 'subject' => 'Test', 'message' => 'Test message']);
        $this->assertTrue($res);
        $this->assertArrayNotHasKey('notify_retry_total', $metrics->counters);
        $this->assertSame(1, $metrics->counters['notify_success_total'] ?? 0);
    }

    public function testRetryMetricNotIncrementedOnMaxRetriesExceeded(): void
    {
        global $t; $t = [];
        Functions\expect('wp_mail')->once()->andReturn(false);
        $metrics = new SpyMetrics();
        $spyDlq = new SpyDlq();
        $dlq = new DlqService($spyDlq);
        $svc = new NotificationService(new CircuitBreaker(), new Logging(), $metrics, null, $dlq);
        $res = $svc->sendMail([
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test message',
            '_attempt' => SMARTALLOC_NOTIFY_MAX_TRIES,
        ]);
        $this->assertFalse($res);
        $this->assertArrayNotHasKey('notify_retry_total', $metrics->counters);
        $this->assertTrue($spyDlq->has('mail'));
    }

    public function testRateLimitExceededPushesDlqAndMetrics(): void
    {
        global $t; $t = [];
        $metrics = new SpyMetrics();
        $spyDlq  = new SpyDlq();
        $dlq     = new DlqService($spyDlq);
        $config  = new ThrottleConfig(0, 1, 60);
        $svc     = new NotificationService(new CircuitBreaker(), new Logging(), $metrics, null, $dlq, $config);
        $svc->send([
            'event_name' => 'user_registered',
            'body'       => ['email' => 'foo1@example.com', 'user_id' => 41],
            'recipient'  => 'foo1@example.com',
        ]);
        try {
            $svc->send([
                'event_name' => 'user_registered',
                'body'       => ['email' => 'foo@example.com', 'user_id' => 42],
                'recipient'  => 'foo@example.com',
            ]);
            $this->fail('ThrottleException not thrown');
        } catch (ThrottleException $e) {
            $this->assertSame(1, $metrics->counters['notify_throttled_total'] ?? 0);
            $this->assertSame(1, $metrics->counters['dlq_push_total'] ?? 0);
            $this->assertSame(1, $metrics->counters['notify_failed_total'] ?? 0);
            $this->assertTrue($spyDlq->has('notify'));
            $payload = $spyDlq->last('notify');
            $this->assertSame('[REDACTED]', $payload['body']['email'] ?? '');
            $this->assertStringStartsWith('user_', $payload['body']['user_id'] ?? '');
        }
    }

}
