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
     * Rank mentors by score using a stable sort.
     *
     * @param array<int,array<string,mixed>> $mentors
     * @param array<string,mixed> $student
     * @return array<int,array<string,mixed>>
     */
    public function rank(array $mentors, array $student): array
    {
        foreach ($mentors as &$m) {
            $m['score'] = $this->score($m, $student);
        }
        unset($m);

        usort($mentors, function ($a, $b): int {
            $cmp = $b['score'] <=> $a['score'];
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
     * @param array<string,mixed> $mentor
     * @param array<string,mixed> $student
     */
    public function score(array $mentor, array $student): float
    {
        $weights = apply_filters('smartalloc_scoring_weights', ['load' => 1.0, 'new' => 0.1]);
        $w1 = (float) ($weights['load'] ?? 1.0);
        $w2 = (float) ($weights['new'] ?? 0.1);
        $capacity = max(1, (int) ($mentor['capacity'] ?? 1));
        $assigned = max(0, (int) ($mentor['assigned'] ?? 0));
        $loadRatio = $assigned / $capacity;
        $boost = ((int) ($mentor['allocations_new'] ?? 0)) === 0 ? 1.0 : 0.0;
        return (1 - $loadRatio) * $w1 + $boost * $w2;
    }
}
