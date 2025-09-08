<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Tests\Integration\Services;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\CircuitBreaker;

final class CircuitBreakerWordPressIntegrationTest extends TestCase {
    public function testTransientPersistence(): void {
        $cb = new CircuitBreaker('wp');
        $cb->failure('op', new \RuntimeException('err'));
        $summary = get_transient('smartalloc_circuit_summary_wp');
        $this->assertEquals(1, $summary['total_failures']);
    }
}
