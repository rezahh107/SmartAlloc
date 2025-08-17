<?php

namespace SmartAlloc\Tests;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\CircuitBreaker;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        $this->circuitBreaker = new CircuitBreaker('test_service', 3, 60);
    }

    public function testCircuitOpenAfterThreshold(): void
    {
        // Circuit should be closed initially
        $this->assertTrue($this->circuitBreaker->isClosed());
        
        // Fail multiple times to open circuit
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->recordFailure();
        }
        
        // Circuit should now be open
        $this->assertTrue($this->circuitBreaker->isOpen());
    }

    public function testCircuitHalfOpenAfterCooldown(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->recordFailure();
        }
        
        // Fast forward cooldown
        $this->circuitBreaker->updateConfig(['cooldown' => 0]);
        
        // Circuit should be half-open
        $this->assertTrue($this->circuitBreaker->isHalfOpen());
    }

    public function testCircuitCloseAfterSuccess(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->recordFailure();
        }
        
        // Fast forward cooldown
        $this->circuitBreaker->updateConfig(['cooldown' => 0]);
        
        // Record success
        $this->circuitBreaker->recordSuccess();
        
        // Circuit should be closed
        $this->assertTrue($this->circuitBreaker->isClosed());
    }

    public function testConfigurableThreshold(): void
    {
        $circuitBreaker = new CircuitBreaker('test_service', 5, 60);
        
        // Should need 5 failures to open
        for ($i = 0; $i < 4; $i++) {
            $circuitBreaker->recordFailure();
        }
        
        // Circuit should still be closed
        $this->assertTrue($circuitBreaker->isClosed());
        
        // 5th failure should open it
        $circuitBreaker->recordFailure();
        $this->assertTrue($circuitBreaker->isOpen());
    }

    public function testConfigurableCooldown(): void
    {
        $circuitBreaker = new CircuitBreaker('test_service', 3, 120);
        
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $circuitBreaker->recordFailure();
        }
        
        // Circuit should be open
        $this->assertTrue($circuitBreaker->isOpen());
        
        // Update cooldown to 0 for testing
        $circuitBreaker->updateConfig(['cooldown' => 0]);
        
        // Circuit should be half-open
        $this->assertTrue($circuitBreaker->isHalfOpen());
    }

    public function testHalfOpenCallback(): void
    {
        $callbackExecuted = false;
        $callback = function() use (&$callbackExecuted) {
            $callbackExecuted = true;
        };
        
        $circuitBreaker = new CircuitBreaker('test_service', 3, 60, $callback);
        
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $circuitBreaker->recordFailure();
        }
        
        // Fast forward cooldown
        $circuitBreaker->updateConfig(['cooldown' => 0]);
        
        // Execute half-open callback
        $circuitBreaker->executeHalfOpenCallback('test_service');
        
        $this->assertTrue($callbackExecuted);
    }

    public function testStatusReport(): void
    {
        $status = $this->circuitBreaker->getStatusReport();
        
        $this->assertArrayHasKey('name', $status);
        $this->assertArrayHasKey('state', $status);
        $this->assertArrayHasKey('failure_count', $status);
        $this->assertArrayHasKey('last_failure_time', $status);
        $this->assertArrayHasKey('threshold', $status);
        $this->assertArrayHasKey('cooldown', $status);
    }

    public function testConfigUpdate(): void
    {
        $originalThreshold = $this->circuitBreaker->getConfig()['threshold'];
        
        $this->circuitBreaker->updateConfig(['threshold' => 10]);
        
        $newThreshold = $this->circuitBreaker->getConfig()['threshold'];
        
        $this->assertEquals(10, $newThreshold);
        $this->assertNotEquals($originalThreshold, $newThreshold);
    }

    public function testReset(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            $this->circuitBreaker->recordFailure();
        }
        
        // Reset the circuit
        $this->circuitBreaker->reset();
        
        // Circuit should be closed
        $this->assertTrue($this->circuitBreaker->isClosed());
        $this->assertEquals(0, $this->circuitBreaker->getStatusReport()['failure_count']);
    }
} 