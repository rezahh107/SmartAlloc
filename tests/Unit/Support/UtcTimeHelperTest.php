<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Support\UtcTimeHelper;

final class UtcTimeHelperTest extends TestCase
{
    public function testCurrentUtcTimestamp(): void
    {
        $timestamp = UtcTimeHelper::getCurrentUtcTimestamp();
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    public function testCurrentUtcDatetimeFormat(): void
    {
        $datetime = UtcTimeHelper::getCurrentUtcDatetime();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime);
        $this->assertTrue(UtcTimeHelper::isValidUtcDatetime($datetime));
    }

    public function testTimestampConversionRoundTrip(): void
    {
        $timestamp = 1640995200; // 2022-01-01 00:00:00 UTC
        $datetime = UtcTimeHelper::timestampToUtcDatetime($timestamp);
        $this->assertSame('2022-01-01 00:00:00', $datetime);
        $this->assertSame($timestamp, UtcTimeHelper::utcDatetimeToTimestamp($datetime));
    }
}
