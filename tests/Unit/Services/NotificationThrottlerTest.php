<?php
// phpcs:ignoreFile
declare(strict_types=1);

use SmartAlloc\Tests\Unit\TestCase as BaseTestCase;
use SmartAlloc\Services\NotificationThrottler;
use Brain\Monkey\Functions;

final class NotificationThrottlerTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('SMARTALLOC_CAP')) {
            define('SMARTALLOC_CAP', 'manage_smartalloc');
        }

        $this->mockTransients();
        Functions\when('get_option')->alias(function ($k, $d = false) { global $o; return $o[$k] ?? $d; });
        Functions\when('update_option')->alias(function ($k, $v) { global $o; $o[$k] = $v; });
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_allows_and_blocks_by_limit(): void
    {
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
