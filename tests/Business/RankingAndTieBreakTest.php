<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\ScoringAllocator;
use SmartAlloc\Tests\Fixtures\{MentorFactory, StudentFactory};

final class RankingAndTieBreakTest extends TestCase
{
    public function test_tie_breaking_by_allocations_new_then_id(): void
    {
        $mentors = [
            MentorFactory::make('F', 1, 1, null, 60, 30, 2),
            MentorFactory::make('F', 1, 1, null, 60, 30, 1),
            MentorFactory::make('F', 1, 1, null, 60, 30, 1),
        ];
        $student = StudentFactory::make([
            'gender' => 'F',
            'center' => 1,
        ]);
        $scorer = new ScoringAllocator();
        $ranked = $scorer->rank($mentors, $student);
        $this->assertSame($mentors[1]['mentor_id'], $ranked[0]['mentor_id']);
        $this->assertSame($mentors[2]['mentor_id'], $ranked[1]['mentor_id']);
        $this->assertSame($mentors[0]['mentor_id'], $ranked[2]['mentor_id']);
    }
}
