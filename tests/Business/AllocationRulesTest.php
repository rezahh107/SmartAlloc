<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\AllocationRules;
use SmartAlloc\Services\ScoringAllocator;
use SmartAlloc\Tests\Fixtures\{MentorFactory, StudentFactory, CrosswalkFactory};

final class AllocationRulesTest extends BaseTestCase
{
    public function test_filters_and_ranking_with_default_capacity(): void
    {
        $mentors = [
            MentorFactory::make('M', 1, 1, 'G1', 60, 10, 0, 101), // wrong gender
            MentorFactory::make('F', 1, 1, 'G2', 60, 10, 0, 101), // wrong group
            MentorFactory::make('F', 1, 1, 'G1', 60, 10, 0, 102), // wrong school
            MentorFactory::make('F', 2, 1, 'G1', 60, 10, 0, 101), // wrong center
            MentorFactory::make('F', 1, 2, 'G1', 60, 10, 0, 101), // wrong manager
            // valid candidates
            MentorFactory::make('F', 1, 1, 'G1', null, 0, 1, 101),   // capacity null -> default 60
            MentorFactory::make('F', 1, 1, 'G1', 60, 30, 1, 101),    // ratio 0.5, allocations_new 1
            MentorFactory::make('F', 1, 1, 'G1', 40, 20, 0, 101),    // ratio 0.5, allocations_new 0
        ];

        $student = StudentFactory::make([
            'gender' => 'F',
            'group_code' => 'G1',
            'center' => 1,
            'manager_id' => 1,
            'school_name' => 'School A',
        ]);

        $crosswalk = [CrosswalkFactory::school('School A', 101)];

        $rules = new AllocationRules();
        $filtered = $rules->filter($mentors, $student, $crosswalk);
        $this->assertCount(3, $filtered, 'Only fully matching mentors remain');

        $scorer = new ScoringAllocator();
        $ranked = $scorer->rank($filtered, $student);

        // Default capacity applied to mentor with null capacity
        $this->assertSame(60, $ranked[0]['capacity']);

        // Ranking order: ratio asc then allocations_new then mentor_id
        $this->assertSame(6, $ranked[0]['mentor_id']);
        $this->assertSame(8, $ranked[1]['mentor_id']);
        $this->assertSame(7, $ranked[2]['mentor_id']);
    }
}
