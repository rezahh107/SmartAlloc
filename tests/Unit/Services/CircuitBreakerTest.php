<?php

declare(strict_types=1);

namespace SmartAlloc\Services {
    function apply_filters($hook, $value, ...$args)
    {
        return ($hook === 'smartalloc_cb_threshold') ? 10 : (($hook === 'smartalloc_cb_cooldown') ? 600 : $value);
    }

    function get_transient($key)
    {
        return $GLOBALS['t'][$key] ?? false;
    }

    function set_transient($key, $value, $ttl)
    {
        $GLOBALS['t'][$key] = $value;
        return true;
    }

    function wp_date($format)
    {
        return time();
    }

    function esc_html($text)
    {
        return $text;
    }
}

namespace SmartAlloc\Tests\Unit\Services {
    use PHPUnit\Framework\TestCase;
    use SmartAlloc\Services\CircuitBreaker;
    use SmartAlloc\Services\CircuitBreakerStatus;

    final class CircuitBreakerTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            $GLOBALS['t'] = [];
        }

        public function testFiltersApplied(): void
        {
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

        public function testLegacyApiMethodsPreserved(): void
        {
            $cb = new CircuitBreaker('test');

            for ($i = 0; $i < 10; $i++) {
                $cb->failure('op', new \RuntimeException('fail'));
            }

            try {
                $cb->guard('op');
                $this->fail('Expected exception not thrown');
            } catch (\RuntimeException $e) {
                // Expected
            }

            $cb->success('op');
            $status = $cb->getStatus();

            $this->assertSame('closed', $status->state);
            $this->assertSame(0, $status->failCount);
        }
    }
}
