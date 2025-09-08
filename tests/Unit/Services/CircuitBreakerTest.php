<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Exceptions\CircuitBreakerException;

class CircuitBreakerTest extends TestCase
{
    public function testInitialization(): void
    {
        $cb = new CircuitBreaker(5, 60);
        $this->assertTrue($cb->isClosed());
        $this->assertEquals(0, $cb->getFailureCount());
        $this->assertEquals(5, $cb->getFailureThreshold());
    }

    public function testSuccessfulOperation(): void
    {
        $cb = new CircuitBreaker();
        $result = $cb->execute(fn ($v) => $v * 2, 5);
        $this->assertEquals(10, $result);
        $this->assertTrue($cb->isClosed());
    }

    public function testCircuitOpensAfterThreshold(): void
    {
        $cb = new CircuitBreaker(1, 60);
        try {
            $cb->execute(function (): void {
                throw new \Exception('fail');
            });
        } catch (\Exception $e) {
        }
        $this->expectException(CircuitBreakerException::class);
        $cb->execute(fn () => 'success');
    }

    public function testResetFunctionality(): void
    {
        $cb = new CircuitBreaker(1, 60);
        try {
            $cb->execute(function (): void {
                throw new \Exception('fail');
            });
        } catch (\Exception $e) {
        }
        $this->assertTrue($cb->isOpen());
        $cb->reset();
        $this->assertTrue($cb->isClosed());
    }
}
