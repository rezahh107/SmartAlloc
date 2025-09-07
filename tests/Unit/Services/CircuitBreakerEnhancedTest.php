<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services {
    use PHPUnit\Framework\TestCase;
    use SmartAlloc\Services\CircuitBreaker;
    use SmartAlloc\Services\Exceptions\CircuitOpenException;

    final class CircuitBreakerEnhancedTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            $GLOBALS['_wp_transients'] = [];
        }

        public function testAutoRecoveryFromExpiredCooldown(): void
        {
            $cb = new CircuitBreaker('test');
            $expiredTime = time() - 100;
            \set_transient('smartalloc_circuit_breaker_test', [
                'state' => 'open',
                'fail_count' => 5,
                'cooldown_until' => $expiredTime,
                'last_error' => 'Previous error',
            ], 3600);

            $status = $cb->getStatus();

            $this->assertSame('half-open', $status->state);
            $this->assertSame(0, $status->failCount);
            $this->assertNull($status->cooldownUntil);
        }

        public function testErrorMessageSanitization(): void
        {
            $cb = new CircuitBreaker('test');
            $longError = str_repeat('A', 150);
            \set_transient('smartalloc_circuit_breaker_test', [
                'state' => 'closed',
                'fail_count' => 1,
                'cooldown_until' => null,
                'last_error' => $longError,
            ], 3600);

            $status = $cb->getStatus();

            $this->assertSame(100, strlen($status->lastError));
            $this->assertSame(str_repeat('A', 100), $status->lastError);
        }

        public function testCircuitOpenExceptionThrown(): void
        {
            $cb = new CircuitBreaker('test');

            $this->expectException(CircuitOpenException::class);
            $this->expectExceptionMessage('Circuit breaker opened due to failure threshold exceeded');

            for ($i = 0; $i < 5; $i++) {
                $cb->recordFailure('Test error');
            }
        }

        public function testTransientInitialization(): void
        {
            unset($GLOBALS['t']['smartalloc_circuit_breaker_test']);
            $cb = new CircuitBreaker('test');

            $status = $cb->getStatus();

            $this->assertSame('closed', $status->state);
            $this->assertSame(0, $status->failCount);
            $this->assertNull($status->cooldownUntil);
            $this->assertNull($status->lastError);
        }
    }
}
