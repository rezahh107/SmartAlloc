<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Notifications\RetryingMailer;

final class RetryingMailerTest extends TestCase
{
    public function test_retries_then_succeeds(): void
    {
        $calls = 0;
        $scheduled = [];
        $mailFn = static function (array $p) use (&$calls): bool {
            $calls++;
            return $calls >= 3;
        };
        $scheduleFn = static function (int $ts, string $hook, array $args) use (&$scheduled): bool {
            $scheduled[] = ['ts' => $ts, 'args' => $args];
            return true;
        };
        $mailer = new RetryingMailer($mailFn, $scheduleFn, null, 3, 1);
        $sent = $mailer->send(['to' => 't', 'subject' => 's', 'message' => 'm'], 1);
        $this->assertTrue($sent);
        $this->assertCount(1, $scheduled);
        $mailer::retryAction($scheduled[0]['args'][0]);
        $this->assertCount(2, $scheduled);
        $mailer::retryAction($scheduled[1]['args'][0]);
        $this->assertSame(3, $calls);
    }
}
