<?php

namespace SmartAlloc\Tests;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\EventStoreWp;

class EventBusRetryTest extends TestCase
{
    private EventBus $eventBus;
    private Logging $logger;
    private EventStoreWp $eventStore;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Logging::class);
        $this->eventStore = $this->createMock(EventStoreWp::class);
        $this->eventBus = new EventBus($this->logger, $this->eventStore);
    }

    public function testRetryMechanism(): void
    {
        $retryCount = 0;
        $listener = function($event, $payload) use (&$retryCount) {
            $retryCount++;
            if ($retryCount < 3) {
                throw new \RuntimeException('Simulated failure');
            }
            return true;
        };

        $this->eventBus->on('TestEvent', $listener);
        
        // First dispatch should fail and retry
        $this->eventBus->dispatch('TestEvent', ['test' => 'data']);
        
        // Should have been called 3 times (initial + 2 retries)
        $this->assertEquals(3, $retryCount);
    }

    public function testRetryTimeout(): void
    {
        $startTime = microtime(true);
        $timeout = 0.1; // 100ms timeout
        
        $listener = function($event, $payload) {
            usleep(200000); // Sleep for 200ms (longer than timeout)
            return true;
        };

        $this->eventBus->on('TestEvent', $listener);
        
        // Set timeout and dispatch
        $this->eventBus->setTimeout($timeout);
        $this->eventBus->dispatch('TestEvent', ['test' => 'data']);
        
        $duration = microtime(true) - $startTime;
        
        // Should complete within reasonable time (not wait for full 200ms)
        $this->assertLessThan(0.2, $duration);
    }

    public function testPriorityOrdering(): void
    {
        $executionOrder = [];
        
        $listener1 = function($event, $payload) use (&$executionOrder) {
            $executionOrder[] = 'low';
            return true;
        };
        
        $listener2 = function($event, $payload) use (&$executionOrder) {
            $executionOrder[] = 'high';
            return true;
        };
        
        $listener3 = function($event, $payload) use (&$executionOrder) {
            $executionOrder[] = 'medium';
            return true;
        };

        // Register with different priorities
        $this->eventBus->on('TestEvent', $listener1, 20); // Low priority
        $this->eventBus->on('TestEvent', $listener2, 5);  // High priority
        $this->eventBus->on('TestEvent', $listener3, 10); // Medium priority
        
        $this->eventBus->dispatch('TestEvent', ['test' => 'data']);
        
        // Should execute in priority order: high, medium, low
        $this->assertEquals(['high', 'medium', 'low'], $executionOrder);
    }

    public function testBridgeRegistration(): void
    {
        $this->eventBus->bridge('test_action');
        
        $bridges = $this->eventBus->getBridges();
        
        $this->assertContains('test_action', $bridges);
    }

    public function testStatsCollection(): void
    {
        $listener = function($event, $payload) {
            return true;
        };

        $this->eventBus->on('TestEvent', $listener);
        $this->eventBus->dispatch('TestEvent', ['test' => 'data']);
        
        $stats = $this->eventBus->getStats();
        
        $this->assertArrayHasKey('dispatched_events', $stats);
        $this->assertArrayHasKey('total_listeners', $stats);
        $this->assertGreaterThan(0, $stats['dispatched_events']);
    }

    public function testClearFunctionality(): void
    {
        $listener = function($event, $payload) {
            return true;
        };

        $this->eventBus->on('TestEvent', $listener);
        
        // Verify listener is registered
        $this->assertTrue($this->eventBus->hasListeners('TestEvent'));
        
        // Clear all listeners
        $this->eventBus->clear();
        
        // Verify listener is removed
        $this->assertFalse($this->eventBus->hasListeners('TestEvent'));
    }
} 