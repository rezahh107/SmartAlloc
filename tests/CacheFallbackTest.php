<?php

namespace SmartAlloc\Tests;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\Cache;

class CacheFallbackTest extends TestCase
{
    private Cache $cache;

    protected function setUp(): void
    {
        $this->cache = new Cache();
    }

    public function testL1ToL2Fallback(): void
    {
        // Test fallback from L1 (Object Cache) to L2 (Transients)
        $key = 'test_fallback_key';
        $value = 'test_value';
        
        // Set value in L2
        $this->cache->set($key, $value, 'L2');
        
        // Get value (should fallback from L1 to L2)
        $result = $this->cache->get($key);
        
        $this->assertEquals($value, $result);
    }

    public function testL2ToL3Fallback(): void
    {
        // Test fallback from L2 (Transients) to L3 (Database)
        $key = 'test_l3_fallback_key';
        $value = 'test_l3_value';
        
        // Set value in L3
        $this->cache->set($key, $value, 'L3');
        
        // Get value (should fallback from L1/L2 to L3)
        $result = $this->cache->get($key);
        
        $this->assertEquals($value, $result);
    }

    public function testHealthCheck(): void
    {
        // Test health status reporting
        $health = $this->cache->getHealthStatus();
        
        $this->assertArrayHasKey('L1', $health);
        $this->assertArrayHasKey('L2', $health);
        $this->assertArrayHasKey('L3', $health);
        $this->assertArrayHasKey('overall', $health);
    }

    public function testConfigurableTTL(): void
    {
        // Test configurable default TTL
        $defaultTTL = $this->cache->getDefaultTTL('L1');
        
        $this->assertIsInt($defaultTTL);
        $this->assertGreaterThan(0, $defaultTTL);
    }

    public function testCacheClearing(): void
    {
        $key = 'test_clear_key';
        $value = 'test_clear_value';
        
        // Set value in multiple layers
        $this->cache->set($key, $value, 'L1');
        $this->cache->set($key, $value, 'L2');
        $this->cache->set($key, $value, 'L3');
        
        // Clear specific layer
        $this->cache->clearL1Cache();
        
        // Value should still be available in other layers
        $result = $this->cache->get($key);
        $this->assertEquals($value, $result);
        
        // Clear all layers
        $this->cache->clearAll();
        
        // Value should not be available
        $result = $this->cache->get($key);
        $this->assertNull($result);
    }
} 