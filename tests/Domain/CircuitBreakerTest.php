<?php

declare(strict_types=1);

use SmartAlloc\Domain\Export\CircuitBreaker;
use SmartAlloc\Infra\Metrics\MetricsCollector;
use SmartAlloc\Tests\BaseTestCase;

final class CircuitBreakerTest extends BaseTestCase
{
    private MetricsCollector $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metrics = new MetricsCollector();
        $this->metrics->reset();
    }

    public function test_transitions(): void
    {
        $breaker = new CircuitBreaker($this->metrics);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure();
        }
        $this->assertFalse($breaker->allow());
        $this->assertSame('open', $breaker->getState());

        $data = $GLOBALS['sa_options']['smartalloc_export_cb'];
        $data['opened_at'] = time() - 301;
        $GLOBALS['sa_options']['smartalloc_export_cb'] = $data;
        $this->assertTrue($breaker->allow());
        $this->assertSame('half', $breaker->getState());
    }
}
