<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ValueObjects;

use SmartAlloc\ValueObjects\CircuitBreakerStatus;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerStatusTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $cooldownTime = time() + 300;
        $status       = new CircuitBreakerStatus(
            'open',
            3,
            5,
            $cooldownTime,
            'Test error'
        );

        $this->assertSame('open', $status->state);
        $this->assertSame(3, $status->failCount);
        $this->assertSame(5, $status->threshold);
        $this->assertSame($cooldownTime, $status->cooldownUntil);
        $this->assertSame('Test error', $status->lastError);
    }

    public function test_state_helper_methods(): void
    {
        $openStatus = new CircuitBreakerStatus('open', 5, 5, time() + 300, null);
        $this->assertTrue($openStatus->isOpen());
        $this->assertFalse($openStatus->isClosed());
        $this->assertFalse($openStatus->isHalfOpen());

        $closedStatus = new CircuitBreakerStatus('closed', 0, 5, null, null);
        $this->assertTrue($closedStatus->isClosed());
        $this->assertFalse($closedStatus->isOpen());
        $this->assertFalse($closedStatus->isHalfOpen());

        $halfOpenStatus = new CircuitBreakerStatus('half-open', 0, 5, null, null);
        $this->assertTrue($halfOpenStatus->isHalfOpen());
        $this->assertFalse($halfOpenStatus->isOpen());
        $this->assertFalse($halfOpenStatus->isClosed());
    }

    public function test_to_array_conversion(): void
    {
        $cooldownTime = time() + 300;
        $status       = new CircuitBreakerStatus('open', 3, 5, $cooldownTime, 'Error message');

        $expected = [
            'state'          => 'open',
            'fail_count'     => 3,
            'threshold'      => 5,
            'cooldown_until' => $cooldownTime,
            'last_error'     => 'Error message',
        ];

        $this->assertSame($expected, $status->toArray());
    }

    public function test_handles_null_values(): void
    {
        $status = new CircuitBreakerStatus('closed', 0, 5, null, null);

        $this->assertNull($status->cooldownUntil);
        $this->assertNull($status->lastError);

        $array = $status->toArray();
        $this->assertNull($array['cooldown_until']);
        $this->assertNull($array['last_error']);
    }
}
