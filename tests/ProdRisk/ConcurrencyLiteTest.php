<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\ProdRisk;

use SmartAlloc\Tests\BaseTestCase;

final class ConcurrencyLiteTest extends BaseTestCase
{
    public function test_idempotency_and_locks_simulated(): void
    {
        $flow = new class {
            private array $seen = [];
            private bool $locked = false;
            public function handle(string $id): array {
                if ($this->locked) {
                    return ['ok' => false, 'code' => 'lock'];
                }
                if (isset($this->seen[$id])) {
                    return ['ok' => false, 'code' => 'duplicate_allocation'];
                }
                $this->seen[$id] = true;
                return ['ok' => true, 'code' => 'ok'];
            }
            public function lock(): void { $this->locked = true; }
        };

        $this->assertSame('ok', $flow->handle('req')['code']);
        $last = null;
        for ($i = 0; $i < 9; $i++) {
            $last = $flow->handle('req');
        }
        $this->assertSame('duplicate_allocation', $last['code']);
        $flow->lock();
        $this->assertSame('lock', $flow->handle('other')['code']);
    }
}
