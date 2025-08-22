<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Score mentors based on load and new allocation boost.
 */
final class ScoringAllocator
{
    /**
     * @param array<int,array<string,mixed>> $mentors
     * @return array<int,array<string,mixed>>
     */
    public function rank(array $mentors): array
    {
        foreach ($mentors as &$m) {
            $m['score'] = $this->score($m);
        }
        unset($m);
        usort($mentors, fn($a, $b) => $b['score'] <=> $a['score']);
        return $mentors;
    }

    /**
     * @param array<string,mixed> $mentor
     */
    public function score(array $mentor): float
    {
        $weights = apply_filters('smartalloc_scoring_weights', ['load' => 1.0, 'new' => 0.1]);
        $w1 = (float) ($weights['load'] ?? 1.0);
        $w2 = (float) ($weights['new'] ?? 0.1);
        $capacity = max(1, (int) ($mentor['capacity'] ?? 1));
        $assigned = max(0, (int) ($mentor['assigned'] ?? 0));
        $loadRatio = $assigned / $capacity;
        $boost = ((int) ($mentor['allocations_new'] ?? 0)) === 0 ? 1.0 : 0.0;
        $score = (1 - $loadRatio) * $w1 + $boost * $w2;
        $score -= ((int) ($mentor['mentor_id'] ?? 0)) / 1000000000;
        return $score;
    }
}
