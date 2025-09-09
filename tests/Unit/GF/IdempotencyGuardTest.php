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
        $cache = new Cache();
        $guard = new IdempotencyGuard($cache);
        $formId = 150;
        $entryId = 123;

        $this->assertFalse($guard->hasProcessed($formId, $entryId));
        $guard->markProcessed($formId, $entryId);
        $this->assertTrue($guard->hasProcessed($formId, $entryId));

        $cache->l1Del("gf:entry:{$formId}:{$entryId}");
    }
}
