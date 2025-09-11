<?php
// phpcs:ignoreFile
declare(strict_types=1);

use SmartAlloc\Tests\Unit\TestCase as BaseTestCase;
use SmartAlloc\Services\NotificationThrottler;
use Brain\Monkey\Functions;

final class NotificationThrottlerTest extends BaseTestCase
{
    public function test_allows_and_blocks_by_limit(): void
    {
        global $o;
        /** @var array<string, mixed> $o */
        $o = [];
        $this->mockTransients();

        Functions\when('get_option')->alias(
            function (string $k, $d = false) use (&$o): mixed {
                return $o[$k] ?? $d;
            }
        );

        Functions\when('update_option')->alias(
            function (string $k, $v) use (&$o): bool {
                $o[$k] = $v;
                return true;
            }
        );

        $GLOBALS['sa_options'] = [];
        $thr = new NotificationThrottler();
        $this->assertTrue($thr->canSend('a'));
        for ($i = 0; $i < 10; $i++) {
            $thr->recordSend('a');
        }
        $this->assertFalse($thr->canSend('a'));
        $this->assertSame(['hits' => 10], $thr->getThrottleStats());
    }
}
