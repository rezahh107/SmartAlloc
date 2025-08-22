<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\{AllocationRules, ScoringAllocator};
use SmartAlloc\Tests\Fixtures\{MentorFactory, StudentFactory};

final class AllocationInvariantsTest extends TestCase
{
    public function test_assigned_never_exceeds_capacity(): void
    {
        mt_srand(1234);
        $rules = new AllocationRules();
        $scorer = new ScoringAllocator();
        for ($i = 0; $i < 50; $i++) {
            $capacity = mt_rand(0, 100);
            $assigned = mt_rand(0, $capacity);
            $mentor = MentorFactory::make('M', 1, 1, null, $capacity, $assigned);
            $student = StudentFactory::make(['gender' => 'M', 'center' => 1]);
            $filtered = $rules->filter([$mentor], $student);
            $ranked = $scorer->rank($filtered, $student);
            if (!empty($ranked)) {
                $m = $ranked[0];
                $this->assertLessThanOrEqual($m['capacity'], $m['assigned']);
            }
        }
    }

    public function test_irrelevant_field_invariance(): void
    {
        $mentor = MentorFactory::make('F', 1, 1);
        $student1 = StudentFactory::make(['gender' => 'F', 'center' => 1]);
        $student2 = $student1;
        $student2['email'] = 'hidden@example.com';
        $rules = new AllocationRules();
        $scorer = new ScoringAllocator();
        $r1 = $scorer->rank($rules->filter([$mentor], $student1), $student1);
        $r2 = $scorer->rank($rules->filter([$mentor], $student2), $student2);
        $this->assertSame($r1, $r2);
    }

    public function test_monotonicity_zero_capacity_removed(): void
    {
        $mentorGood = MentorFactory::make('M', 1, 1, null, 60, 10);
        $mentorZero = MentorFactory::make('M', 1, 1, null, 0, 0);
        $student = StudentFactory::make(['gender' => 'M', 'center' => 1]);
        $rules = new AllocationRules();
        $scorer = new ScoringAllocator();
        $withZero = $rules->filter([$mentorGood, $mentorZero], $student);
        $withoutZero = $rules->filter([$mentorGood], $student);
        $this->assertSame($withoutZero, $withZero);
    }
}
