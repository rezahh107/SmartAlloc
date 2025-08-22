<?php

declare(strict_types=1);

namespace SmartAlloc\Contracts;

/**
 * Strategy interface for mentor scoring and ranking.
 */
interface ScoringAllocatorInterface
{
    /**
     * Rank mentors by score (descending) with stable ordering for ties.
     *
     * @param array<int,array<string,mixed>> $mentors
     * @param array<string,mixed> $student
     * @return array<int,array<string,mixed>>
     */
    public function rank(array $mentors, array $student): array;

    /**
     * Score a mentor candidate for a given student.
     *
     * @param array<string,mixed> $mentor
     * @param array<string,mixed> $student
     */
    public function score(array $mentor, array $student): float;
}
