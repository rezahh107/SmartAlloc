<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Health;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Health\HealthReporter;
use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\ValueObjects\CircuitBreakerStatus;

final class HealthReporterTest extends TestCase
{
    public function testHealthyCircuitReturnsSuccess(): void
    {
        $status  = new CircuitBreakerStatus('closed', 0, 5, null, null);
        $breaker = $this->createMock(CircuitBreaker::class);
        $breaker->method('getStatus')->willReturn($status);

        $reporter = new HealthReporter($breaker);
        $result   = $reporter->get_health_status();

        $this->assertTrue($result['success']);
        $this->assertSame('healthy', $result['data']['status']);
    }

    public function testDegradedCircuitReturnsWarning(): void
    {
        $status  = new CircuitBreakerStatus('half-open', 2, 5, time() - 100, time() + 100);
        $breaker = $this->createMock(CircuitBreaker::class);
        $breaker->method('getStatus')->willReturn($status);

        $reporter = new HealthReporter($breaker);
        $result   = $reporter->get_health_status();

        $this->assertTrue($result['success']);
        $this->assertSame('degraded', $result['data']['status']);
        $this->assertSame(2, $result['data']['failure_count']);
    }
}
