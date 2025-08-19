<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\GFAdvanced;

require_once dirname(__DIR__) . '/bootstrap.gf.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;

final class FlowRoutingTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Functions::class)) {
            self::markTestSkipped('Brain Monkey unavailable');
        }
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_flow_engine_simulation_and_rest_or_skip(): void
    {
        $flow = new class {
            private int $capacity = 1;
            private array $done = [];
            private bool $locked = false;
            public function handle(string $id): array {
                if ($this->locked) {
                    return ['ok' => false, 'code' => 'lock'];
                }
                if (in_array($id, $this->done, true)) {
                    return ['ok' => false, 'code' => 'duplicate_allocation'];
                }
                if ($this->capacity < 1) {
                    return ['ok' => false, 'code' => 'capacity_exceeded'];
                }
                $this->done[] = $id;
                $this->capacity--;
                return ['ok' => true, 'code' => 'ok'];
            }
            public function lock(): void { $this->locked = true; }
        };

        $this->assertSame('ok', $flow->handle('a')['code']);
        $this->assertSame('duplicate_allocation', $flow->handle('a')['code']);
        $this->assertSame('capacity_exceeded', $flow->handle('b')['code']);
        $flow->lock();
        $this->assertSame('lock', $flow->handle('c')['code']);

        if (!function_exists('rest_do_request')) {
            self::markTestSkipped('REST routes unavailable');
        }
        self::markTestSkipped('TODO: integrate with real REST routes');
    }
}
