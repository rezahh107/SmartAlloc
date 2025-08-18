<?php

declare(strict_types=1);

namespace SmartAlloc\Domain\Allocation;

use InvalidArgumentException;
use SmartAlloc\Infra\Settings\Settings;

/**
 * Allocate student resources based on validated input.
 *
 * @phpcs:ignoreFile
 */
class StudentAllocator
{
    /**
     * @param array<string,mixed> $config Configuration or repository dependencies
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /**
     * @param array<string,mixed> $studentData
     */
    public function allocate(array $studentData): AllocationResult
    {
        $id = $studentData['id'] ?? null;
        if (!is_int($id) || $id <= 0) {
            throw new InvalidArgumentException('Invalid student id');
        }

        $score = isset($studentData['fuzzy_score']) ? (float) $studentData['fuzzy_score'] : 0.0;
        $capacity = isset($studentData['capacity']) ? (int) $studentData['capacity'] : Settings::getDefaultCapacity();

        $auto = Settings::getFuzzyAutoThreshold();
        $min  = Settings::getFuzzyManualMin();
        $max  = Settings::getFuzzyManualMax();

        $decision = 'reject';
        if ($score >= $auto) {
            $decision = 'auto';
        } elseif ($score >= $min && $score <= $max) {
            $decision = 'manual';
        }

        return new AllocationResult([
            'student_id' => $id,
            'decision'   => $decision,
            'capacity'   => $capacity,
        ]);
    }
}
