<?php

/**
 * CircuitBreaker Unit Tests
 *
 * @package SmartAlloc
 */

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Exceptions\CircuitBreakerException;

/**
 * CircuitBreaker Test Class
 */
class CircuitBreakerTest extends TestCase
{
    /**
     * Test circuit breaker initialization
     *
     * @return void
     */
    public function testInitialization(): void
    {
        $circuitBreaker = new CircuitBreaker(5, 60);

        $this->assertTrue($circuitBreaker->isClosed());
        $this->assertFalse($circuitBreaker->isOpen());
        $this->assertFalse($circuitBreaker->isHalfOpen());
        $this->assertEquals(0, $circuitBreaker->getFailureCount());
        $this->assertEquals(5, $circuitBreaker->getFailureThreshold());
    }

    /**
     * Test successful operation execution
     *
     * @return void
     */
    public function testSuccessfulOperation(): void
    {
        $circuitBreaker = new CircuitBreaker(3, 60);

        $result = $circuitBreaker->execute(function ($value) {
            return $value * 2;
        }, 5);

        $this->assertEquals(10, $result);
        $this->assertTrue($circuitBreaker->isClosed());
        $this->assertEquals(0, $circuitBreaker->getFailureCount());
    }

    /**
     * Test circuit opening after threshold
     *
     * @return void
     */
    public function testCircuitOpensAfterThreshold(): void
    {
        $circuitBreaker = new CircuitBreaker(2, 60);

        for ($i = 0; $i < 2; $i++) {
            try {
                $circuitBreaker->execute(function () {
                    throw new \Exception('Test failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        $this->assertTrue($circuitBreaker->isOpen());
        $this->assertEquals(2, $circuitBreaker->getFailureCount());
    }

    /**
     * Test circuit breaker exception when open
     *
     * @return void
     */
    public function testExceptionWhenCircuitOpen(): void
    {
        $this->expectException(CircuitBreakerException::class);

        $circuitBreaker = new CircuitBreaker(1, 60);

        try {
            $circuitBreaker->execute(function () {
                throw new \Exception('Test failure');
            });
        } catch (\Exception $e) {
            // Expected
        }

        $circuitBreaker->execute(function () {
            return 'success';
        });
    }

    /**
     * Test half-open callback execution
     *
     * @return void
     */
    public function testHalfOpenCallback(): void
    {
        $callbackExecuted = false;
        $callback         = function () use (&$callbackExecuted) {
            $callbackExecuted = true;
        };

        $circuitBreaker = new CircuitBreaker(1, 1, $callback);

        try {
            $circuitBreaker->execute(function () {
                throw new \Exception('Test failure');
            });
        } catch (\Exception $e) {
            // Expected
        }

        sleep(2);

        try {
            $circuitBreaker->execute(function () {
                return 'success';
            });
        } catch (CircuitBreakerException $e) {
            // This will trigger half-open transition
        }

        $this->assertTrue($callbackExecuted);
    }

    /**
     * Test statistics retrieval
     *
     * @return void
     */
    public function testStatistics(): void
    {
        $circuitBreaker = new CircuitBreaker(3, 60);

        $stats = $circuitBreaker->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('state', $stats);
        $this->assertArrayHasKey('failure_count', $stats);
        $this->assertArrayHasKey('failure_threshold', $stats);
        $this->assertEquals('closed', $stats['state']);
        $this->assertEquals(0, $stats['failure_count']);
        $this->assertEquals(3, $stats['failure_threshold']);
    }

    /**
     * Test circuit reset functionality
     *
     * @return void
     */
    public function testResetFunctionality(): void
    {
        $circuitBreaker = new CircuitBreaker(1, 60);

        try {
            $circuitBreaker->execute(function () {
                throw new \Exception('Test failure');
            });
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertTrue($circuitBreaker->isOpen());

        $circuitBreaker->reset();

        $this->assertTrue($circuitBreaker->isClosed());
        $this->assertEquals(0, $circuitBreaker->getFailureCount());
    }
}
