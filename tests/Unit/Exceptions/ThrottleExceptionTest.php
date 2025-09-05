<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Exceptions\ThrottleException;

final class ThrottleExceptionTest extends TestCase
{
    public function test_throttle_exception_includes_retry_after(): void
    {
        $exception = new ThrottleException('Rate limited', 120);
        $this->assertSame('Rate limited', $exception->getMessage());
        $this->assertSame(120, $exception->getRetryAfter());
        $this->assertSame(429, $exception->getCode());
    }
}
