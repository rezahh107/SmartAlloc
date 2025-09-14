<?php

namespace SmartAlloc\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Infra\SystemClock;

class SystemClockTest extends TestCase
{
    public function testNowReturnsUtc(): void
    {
        $clock = new SystemClock();
        $now = $clock->now();
        $this->assertSame('UTC', $now->getTimezone()->getName());
    }
}
