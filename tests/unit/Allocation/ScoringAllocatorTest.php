<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\ScoringAllocator;

final class ScoringAllocatorTest extends BaseTestCase
{
    public function testRanksByScoreAndTiesById(): void
    {
        $allocator = new ScoringAllocator();
        $mentors = [
            ['mentor_id' => 2, 'capacity' => 10, 'assigned' => 5, 'allocations_new' => 0],
            ['mentor_id' => 1, 'capacity' => 10, 'assigned' => 5, 'allocations_new' => 0],
            ['mentor_id' => 3, 'capacity' => 10, 'assigned' => 2, 'allocations_new' => 1],
        ];
        $ranked = $allocator->rank($mentors, []);
        $this->assertSame(1, $ranked[0]['mentor_id']);
        $this->assertSame(2, $ranked[1]['mentor_id']);
        $this->assertSame(3, $ranked[2]['mentor_id']);
    }
}
