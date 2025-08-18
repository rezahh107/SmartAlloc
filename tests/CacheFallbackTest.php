<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Services\Cache;
use SmartAlloc\Tests\BaseTestCase;

final class CacheFallbackTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testL1UsesObjectCacheWhenAvailable(): void
    {
        $cache = new Cache();
        $key = 'obj_key';
        $value = 'value1';

        Functions\expect('wp_using_ext_object_cache')->andReturn(true);

        $cache->l1Set($key, $value, 10);
        $this->assertSame($value, $GLOBALS['sa_wp_cache']['smartalloc'][$key]);
        $this->assertSame($value, $cache->l1Get($key));
    }

    public function testL1FallsBackToTransients(): void
    {
        $cache = new Cache();
        $key = 'trans_key';
        $value = 'value2';

        Functions\expect('wp_using_ext_object_cache')->andReturn(false);

        $cache->l1Set($key, $value, 10);
        $this->assertSame($value, get_transient('smartalloc_' . $key));
        $this->assertSame($value, $cache->l1Get($key));
    }

    public function testFlushAllClearsTransients(): void
    {
        $cache = new Cache();
        $key = 'flush_key';
        $value = 'flush';

        Functions\expect('wp_using_ext_object_cache')->andReturn(false);
        $cache->l1Set($key, $value, 10);
        $this->assertSame($value, $cache->l1Get($key));

        $cache->flushAllForTests();
        $this->assertFalse(get_transient('smartalloc_' . $key));
    }
}
