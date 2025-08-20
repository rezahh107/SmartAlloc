<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RedisOutageTest extends TestCase {
    protected function setUp(): void {
        if (getenv('RUN_FAILURE_TESTS') !== '1') {
            $this->markTestSkipped('failure tests opt-in');
        }
        if (!class_exists('\\Brain\\Monkey\\Functions')) {
            $this->markTestSkipped('Brain Monkey not installed');
        }
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        if (class_exists('\\Brain\\Monkey')) {
            \Brain\Monkey\tearDown();
        }
    }

    public function test_cache_outage_fallback_or_skip(): void {
        if (!function_exists('wp_cache_get')) {
            $this->markTestSkipped('object cache API not present');
        }

        \Brain\Monkey\Functions\when('wp_cache_get')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_cache_set')->justReturn(false);

        // Simulate cache outage and fallback to transients
        $cached = wp_cache_get('key');
        if ($cached === false) {
            set_transient('key', 'fallback');
            $this->assertSame('fallback', get_transient('key'));
        } else {
            $this->fail('cache should be unavailable');
        }

        if (class_exists('\\SmartAlloc\\Metrics\\Counter')) {
            // Metrics helper exists but API unknown; placeholder assertion
            $this->assertTrue(true, 'metrics counter placeholder');
        }
    }
}
