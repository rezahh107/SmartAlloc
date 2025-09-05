<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Services\CircuitBreakerStatus;
use SmartAlloc\Tests\Unit\TestCase;
use Brain\Monkey;

final class CircuitBreakerTest extends TestCase
{
    private array $transientStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transientStorage =& $this->setupWordPressMocks();
    }

    public function testFiltersApplied(): void
    {
        Monkey\Functions\when('apply_filters')->alias(function ($hook, $value, ...$args) {
            if ($hook === 'smartalloc_cb_threshold') {
                return 10;
            }
            if ($hook === 'smartalloc_cb_cooldown') {
                return 600;
            }
            return $value;
        });

        $cb = new CircuitBreaker('test');
        $status = $cb->getStatus();

        $this->assertSame(10, $status->threshold);
    }

    public function testGetStatusReturnsStatusObject(): void
    {
        $cb = new CircuitBreaker('test');
        $status = $cb->getStatus();

        $this->assertInstanceOf(CircuitBreakerStatus::class, $status);
        $this->assertSame('closed', $status->state);
        $this->assertSame(0, $status->failCount);
    }

    public function testTransientPersistence(): void
    {
        $cb = new CircuitBreaker('test');
        $cb->recordFailure('Test error');

        // Verify transient was set
        $this->assertArrayHasKey('smartalloc_circuit_breaker_test', $this->transientStorage);
        $data = $this->transientStorage['smartalloc_circuit_breaker_test'];
        $this->assertSame('closed', $data['state']);
        $this->assertSame(1, $data['fail_count']);
    }
}

