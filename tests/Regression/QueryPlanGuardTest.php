<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Regression;

use PHPUnit\Framework\TestCase;

final class QueryPlanGuardTest extends TestCase
{
    public function testNPlusOneDetectionPlaceholder(): void
    {
        $this->markTestSkipped('N+1 detection not implemented');
    }
}

