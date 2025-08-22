<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Domain;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Domain\Allocation\StudentAllocator;

final class AllocatorThresholdsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_decisions_change_with_thresholds(): void
    {
        $options = [
            'fuzzy_auto_threshold' => 0.9,
            'fuzzy_manual_min' => 0.8,
            'fuzzy_manual_max' => 0.89,
            'default_capacity' => 60,
        ];
        $GLOBALS['sa_options'] = ['smartalloc_settings' => $options];

        $allocator = new StudentAllocator();

        $auto = $allocator->allocate(['id' => 1, 'fuzzy_score' => 0.92])->to_array();
        $manual = $allocator->allocate(['id' => 2, 'fuzzy_score' => 0.85])->to_array();
        $reject = $allocator->allocate(['id' => 3, 'fuzzy_score' => 0.5])->to_array();

        $this->assertSame('auto', $auto['decision']);
        $this->assertSame('manual', $manual['decision']);
        $this->assertSame('reject', $reject['decision']);

        $options['fuzzy_auto_threshold'] = 0.95;
        $options['fuzzy_manual_min'] = 0.86;
        $options['fuzzy_manual_max'] = 0.94;
        $GLOBALS['sa_options'] = ['smartalloc_settings' => $options];

        $manual2 = $allocator->allocate(['id' => 4, 'fuzzy_score' => 0.92])->to_array();
        $reject2 = $allocator->allocate(['id' => 5, 'fuzzy_score' => 0.85])->to_array();

        $this->assertSame('manual', $manual2['decision']);
        $this->assertSame('reject', $reject2['decision']);
    }
}

