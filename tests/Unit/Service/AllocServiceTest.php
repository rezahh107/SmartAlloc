<?php

namespace SmartAlloc\Tests\Unit\Service;

use Mockery as m;
use SmartAlloc\Domain\Ports\DbPort;
use SmartAlloc\Domain\Ports\CapabilityPort;
use SmartAlloc\Domain\Ports\ClockPort;
use SmartAlloc\Domain\Service\AllocService;

class AllocServiceTest extends \SmartAlloc\Tests\BaseTestCase
{
    public function testAllocateWhenUserHasCapability(): void
    {
        /** @var DbPort&\Mockery\MockInterface $db */
        $db = m::mock(DbPort::class);
        /** @var CapabilityPort&\Mockery\MockInterface $cap */
        $cap = m::mock(CapabilityPort::class);
        /** @var ClockPort&\Mockery\MockInterface $clock */
        $clock = m::mock(ClockPort::class);

        $cap->shouldReceive('can')->with('manage_alloc', 10)->andReturn(true); // @phpstan-ignore-line
        $clock->shouldReceive('now')
            ->andReturn(new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC')));
        $db->shouldReceive('execute')->with( // @phpstan-ignore-line
            'INSERT INTO sa_allocs (user_id, amount, created_at) VALUES (%d, %d, %s)',
            array(10, 500, '2025-01-01 00:00:00')
        )->once()
            ->andReturn(1);

        $svc = new AllocService($db, $cap, $clock);
        $this->assertTrue($svc->allocate(10, 500));
    }

    public function testAllocateDeniedWhenUserLacksCapability(): void
    {
        /** @var DbPort&\Mockery\MockInterface $db */
        $db = m::mock(DbPort::class);
        /** @var CapabilityPort&\Mockery\MockInterface $cap */
        $cap = m::mock(CapabilityPort::class);
        /** @var ClockPort&\Mockery\MockInterface $clock */
        $clock = m::mock(ClockPort::class);

        $cap->shouldReceive('can')->with('manage_alloc', 10)->andReturn(false); // @phpstan-ignore-line

        $svc = new AllocService($db, $cap, $clock);
        $this->assertFalse($svc->allocate(10, 500));
    }
}
