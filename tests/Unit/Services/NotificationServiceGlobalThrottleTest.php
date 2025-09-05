<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SmartAlloc\Exceptions\ThrottleException;
use SmartAlloc\Services\{NotificationService, CircuitBreaker, DlqService};
use SmartAlloc\ValueObjects\ThrottleConfig;
use SmartAlloc\Tests\TestDoubles\SpyDlq;
use SmartAlloc\Infrastructure\Contracts\DlqRepository;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Tests\Helpers\SpyMetrics;


final class FailingDlq implements DlqRepository
{
    public function insert(string $topic, array $payload, \DateTimeImmutable $createdAtUtc): bool
    {
        throw new \RuntimeException('DLQ unavailable');
    }
    public function has(string $topic): bool { return false; }
    public function last(string $topic): ?array { return null; }
    public function listRecent(int $limit): array { return []; }
    public function get(int $id): ?array { return null; }
    public function delete(int $id): bool { return false; }
    public function count(): int { return 0; }
}

final class SpyLogger implements LoggerInterface
{
    /** @var array<int,array{0:string,1:array}> */
    public array $errors = [];
    public function debug(string $message, array $context = []): void {}
    public function info(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void
    {
        $this->errors[] = [$message, $context];
    }
}

final class NotificationServiceGlobalThrottleTest extends TestCase
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

    public function test_global_rate_limit_exhaustion_increments_metrics(): void
    {
        global $t; $t = [];
        $metrics = new SpyMetrics();
        $spyDlq  = new SpyDlq();
        $logger  = new SpyLogger();
        $dlq     = new DlqService($spyDlq, null);
        $svc     = new NotificationService(new CircuitBreaker(), $logger, $metrics, null, $dlq, new ThrottleConfig(1, 1, 60));

        $svc->send([
            'event_name' => 'user_registered',
            'body'       => ['email' => 'a@example.com', 'user_id' => 1],
            'recipient'  => 'a@example.com',
        ]);

        $this->expectException(ThrottleException::class);
        $svc->send([
            'event_name' => 'user_registered',
            'body'       => ['email' => 'b@example.com', 'user_id' => 2],
            'recipient'  => 'b@example.com',
        ]);

        $this->assertSame(1, $metrics->counters['notify_throttled_total'] ?? 0);
        $this->assertSame(1, $metrics->counters['notify_failed_total'] ?? 0);
        $this->assertSame(1, $metrics->counters['dlq_push_total'] ?? 0);
        $this->assertTrue($spyDlq->has('notify'));
        $payload = $spyDlq->last('notify');
        $this->assertSame('global_rate_limit_exceeded', $payload['reason'] ?? '');
        $this->assertSame(1, $payload['limit'] ?? 0);
        $this->assertArrayHasKey('timestamp', $payload);
        $this->assertSame('[REDACTED]', $payload['body']['email'] ?? '');
    }

    public function test_global_throttle_handles_dlq_push_failure(): void
    {
        global $t; $t = [];
        $metrics = new SpyMetrics();
        $logger  = new SpyLogger();
        $dlq = new DlqService(new FailingDlq(), null);
        $svc = new NotificationService(new CircuitBreaker(), $logger, $metrics, null, $dlq, new ThrottleConfig(0, 0, 60));

        $this->expectException(ThrottleException::class);
        $svc->send([
            'event_name' => 'user_registered',
            'body'       => ['email' => 'c@example.com', 'user_id' => 3],
            'recipient'  => 'c@example.com',
        ]);

        $this->assertSame('dlq.push_failed', $logger->errors[0][0] ?? '');
    }
}

