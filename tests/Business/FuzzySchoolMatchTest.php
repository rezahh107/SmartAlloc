<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\AllocationRules;
use SmartAlloc\Tests\Fixtures\{MentorFactory, StudentFactory, CrosswalkFactory};
use SmartAlloc\Utils\Fuzzy;

final class FuzzySchoolMatchTest extends BaseTestCase
{
    public function test_similarity_decisions(): void
    {
        $this->assertSame('accept', Fuzzy::decide(0.95));
        $this->assertSame('manual', Fuzzy::decide(0.85));
        $this->assertSame('reject', Fuzzy::decide(0.70));
    }

    public function test_manual_review_triggers_event_and_blocks_allocation(): void
    {
        $bus = new class {
            public array $events = [];
            public function dispatch(string $event, array $payload, string $v): void
            {
                $this->events[] = $event;
            }
        };

        $rules = new AllocationRules($bus);
        $mentors = [MentorFactory::make('M', 1, 1, null, 60, 0, 0, 101)];
        $student = StudentFactory::make([
            'gender' => 'M',
            'center' => 1,
            'manager_id' => 1,
            'school_name' => 'schoool', // one extra o
        ]);
        $crosswalk = [CrosswalkFactory::school('school', 101)];

        $filtered = $rules->filter($mentors, $student, $crosswalk);
        $this->assertSame([], $filtered);
        $this->assertSame(['AllocationPendingReview'], $bus->events);
    }
}
