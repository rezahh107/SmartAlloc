<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Contracts\ScoringAllocatorInterface;

/**
 * Default scoring allocator implementation.
 */
final class ScoringAllocator implements ScoringAllocatorInterface
{
    /**
     * Rank mentors using business rules:
     * ratio (assigned/capacity) ascending → allocations_new ascending → mentor_id ascending.
     *
     * @param array<int,array<string,mixed>> $mentors
     * @param array<string,mixed> $student
     * @return array<int,array<string,mixed>>
     */
    public function rank(array $mentors, array $student): array
    {
        foreach ($mentors as &$m) {
            $cap = (int) ($m['capacity'] ?? 0);
            if ($cap <= 0) {
                $cap = 60;
            }
            $m['capacity'] = $cap;
            $m['score'] = $this->score($m, $student);
        }
        unset($m);

        usort($mentors, function ($a, $b): int {
            $cmp = ($a['score'] ?? 0) <=> ($b['score'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = ((int) ($a['allocations_new'] ?? 0)) <=> ((int) ($b['allocations_new'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            return ((int) ($a['mentor_id'] ?? 0)) <=> ((int) ($b['mentor_id'] ?? 0));
        });

        return $mentors;
    }

    /**
     * Score a mentor candidate for a student.
     *
     * Score is defined as the load ratio (assigned/capacity) to allow ascending sort.
     * Default capacity is 60 when not provided or zero.
     *
     * @param array<string,mixed> $mentor
     * @param array<string,mixed> $student
     */
    public function score(array $mentor, array $student): float
    {
        $capacity = (int) ($mentor['capacity'] ?? 0);
        if ($capacity <= 0) {
            $capacity = 60;
        }
        $assigned = max(0, (int) ($mentor['assigned'] ?? 0));
        return $capacity > 0 ? $assigned / $capacity : 1.0;
    }
}
