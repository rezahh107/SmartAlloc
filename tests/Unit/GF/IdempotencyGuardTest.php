<?php

declare(strict_types=1);

namespace Tests\Unit\GF;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Infra\GF\IdempotencyGuard;
use SmartAlloc\Services\Cache;

final class IdempotencyGuardTest extends TestCase
{
    public function testMarksAndChecksEntries(): void
    {
        $cache    = new Cache();
        $guard    = new IdempotencyGuard($cache);
        $form_id  = 150;
        $entry_id = 123;

        $this->assertFalse($guard->has_processed($form_id, $entry_id));
        $guard->mark_processed($form_id, $entry_id);
        $this->assertTrue($guard->has_processed($form_id, $entry_id));

        $cache->l1Del("gf:entry:{$form_id}:{$entry_id}");
    }
}
