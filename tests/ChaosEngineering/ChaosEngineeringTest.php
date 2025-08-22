<?php
namespace SmartAlloc\Tests\ChaosEngineering;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Testing\Chaos\FaultFlags;
require_once __DIR__ . '/ChaosTestHelpers.php';

final class ChaosEngineeringTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FaultFlags::reset();
    }

    public function test_database_connection_failure_gracefully_degrades(): void
    {
        FaultFlags::$db_down = true;
        FaultFlags::apply();
        $res = ChaosTestHelpers::simulateDbQuery();
        $this->assertArrayHasKey('error', $res);
        $this->assertSame('INTERNAL_ERROR', $res['error']['code']);
    }

    public function test_high_memory_pressure_batches_work(): void
    {
        FaultFlags::$memory_pressure = 1024;
        FaultFlags::apply();
        $peak = ChaosTestHelpers::processBatch(5);
        $this->assertLessThan(64 * 1024 * 1024, $peak);
    }

    public function test_network_latency_timeouts_and_retries(): void
    {
        FaultFlags::$high_latency_ms = 10;
        FaultFlags::apply();
        $start = microtime(true);
        $this->assertTrue(ChaosTestHelpers::notify());
        $elapsed = (microtime(true) - $start) * 1000;
        $this->assertGreaterThanOrEqual(10, $elapsed);
    }

    public function test_partial_service_failure_circuit_breaker_opens(): void
    {
        FaultFlags::$partial_service_down = ['notify' => true];
        FaultFlags::apply();
        $this->expectException(\RuntimeException::class);
        ChaosTestHelpers::notify();
    }
}
