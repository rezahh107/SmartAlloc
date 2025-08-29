<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Notifications\RetryingMailer;

final class RetryingMailerTest extends TestCase
{
    public function test_success_on_first_attempt(): void
    {
        $mailFn = static fn(array $p): bool => true;
        $scheduled = 0;
        $scheduleFn = static function (int $ts, string $hook, array $args) use (&$scheduled): bool {
            $scheduled++;
            return true;
        };
        $mailer = new RetryingMailer($mailFn, $scheduleFn);
        $this->assertTrue($mailer->send(['to' => 't', 'subject' => 's', 'message' => 'm']));
        $this->assertSame(0, $scheduled);
    }

    public function test_retry_scheduling_on_failure(): void
    {
        $mailFn = static fn(array $p): bool => false;
        $scheduled = 0;
        $scheduleFn = static function (int $ts, string $hook, array $args) use (&$scheduled): bool {
            $scheduled++;
            return true;
        };
        $mailer = new RetryingMailer($mailFn, $scheduleFn);
        $this->assertTrue($mailer->send(['to' => 't', 'subject' => 's', 'message' => 'm']));
        $this->assertSame(1, $scheduled);
    }

    public function test_success_on_nth_attempt(): void
    {
        $calls = 0;
        $mailFn = static function (array $p) use (&$calls): bool {
            $calls++;
            return $calls >= 2;
        };
        $scheduled = [];
        $scheduleFn = static function (int $ts, string $hook, array $args) use (&$scheduled): bool {
            $scheduled[] = $args;
            return true;
        };
        $mailer = new RetryingMailer($mailFn, $scheduleFn, null, 3, 1, static fn(): int => 1000);
        $mailer->send(['to' => 't', 'subject' => 's', 'message' => 'm']);
        $this->assertCount(1, $scheduled);
        $mailer::retryAction($scheduled[0][0]);
        $this->assertSame(2, $calls);
    }

    public function test_final_failure_after_max_attempts(): void
    {
        $mailFn = static fn(array $p): bool => false;
        $scheduled = 0;
        $scheduleFn = static function (int $ts, string $hook, array $args) use (&$scheduled): bool {
            $scheduled++;
            return true;
        };
        $mailer = new RetryingMailer($mailFn, $scheduleFn, null, 2, 1);
        $this->assertTrue($mailer->send(['to' => 't', 'subject' => 's', 'message' => 'm'], 1));
        $this->assertFalse($mailer->send(['to' => 't', 'subject' => 's', 'message' => 'm'], 2));
        $this->assertSame(1, $scheduled);
    }

    public function test_exponential_backoff_calculation(): void
    {
        $mailFn = static fn(array $p): bool => false;
        $delays = [];
        $now = 1000;
        $timeFn = static fn(): int => $now;
        $scheduleFn = static function (int $ts, string $hook, array $args) use (&$delays, $now): bool {
            $delays[] = $ts - $now;
            return true;
        };
        $mailer = new RetryingMailer($mailFn, $scheduleFn, null, 4, 60, $timeFn);
        $mailer->send(['to' => 't', 'subject' => 's', 'message' => 'm'], 1);
        $this->assertSame([60], $delays);
        $mailer::retryAction(['payload' => ['to' => 't', 'subject' => 's', 'message' => 'm'], 'attempt' => 2]);
        $this->assertSame([60, 120], $delays);
        $mailer::retryAction(['payload' => ['to' => 't', 'subject' => 's', 'message' => 'm'], 'attempt' => 3]);
        $this->assertSame([60, 120, 240], $delays);
    }
}
