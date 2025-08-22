<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\StatsService;

final class FairnessGiniTest extends BaseTestCase
{
    public function testBalancedLoadsHaveLowGini(): void
    {
        $loads = array_fill(0, 1000, 1);
        $g = StatsService::gini($loads);
        $this->assertLessThan(0.01, $g);
    }
}
