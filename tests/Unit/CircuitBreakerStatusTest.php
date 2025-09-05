<?php
// phpcs:ignoreFile

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\CircuitBreakerStatus;

final class CircuitBreakerStatusTest extends TestCase
{
    public function testValidState(): void
    {
        $status = new CircuitBreakerStatus('open', 0, 3);
        $this->assertSame('open', $status->state);
    }

    public function testInvalidState(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CircuitBreakerStatus('invalid', 0, 3);
    }

    public function testLastErrorSanitization(): void
    {
        $longError = str_repeat('A', 150);
        $status = new CircuitBreakerStatus('closed', 1, 3, null, $longError);
        $this->assertSame(100, strlen($status->lastError));
    }

    public function testHelperMethods(): void
    {
        $open = new CircuitBreakerStatus('open', 0, 3);
        $closed = new CircuitBreakerStatus('closed', 0, 3);
        $half = new CircuitBreakerStatus('half-open', 0, 3);

        $this->assertTrue($open->isOpen());
        $this->assertFalse($open->isClosed());

        $this->assertTrue($closed->isClosed());
        $this->assertFalse($closed->isOpen());

        $this->assertTrue($half->isHalfOpen());
        $this->assertFalse($half->isClosed());
    }
}
