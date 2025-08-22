<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Utils\Fuzzy;

/**
 * Pure helper enforcing allocation filtering and fuzzy school logic.
 */
final class AllocationRules
{
    private $bus;

    public function __construct($bus = null)
    {
        $this->bus = $bus;
    }

    /**
     * Apply mentor candidate filters in defined order.
     *
     * @param array<int,array<string,mixed>> $mentors
     * @param array<string,mixed> $student
     * @param array<int,array{name:string,code:int}> $crosswalk
     * @return array<int,array<string,mixed>>
     */
    public function filter(array $mentors, array $student, array $crosswalk = []): array
    {
        // Gender
        $mentors = array_values(array_filter($mentors, function ($m) use ($student) {
            return ($m['gender'] ?? '') === ($student['gender'] ?? '');
        }));

        // Group/Grade
        if (isset($student['group_code']) && $student['group_code'] !== null) {
            $mentors = array_values(array_filter($mentors, function ($m) use ($student) {
                return ($m['group_code'] ?? null) === $student['group_code'];
            }));
        }

        // School (for school-based mentors)
        if (!empty($student['school_name'])) {
            $match = $this->resolveSchool($student['school_name'], $crosswalk);
            if ($match['decision'] === 'manual') {
                if ($this->bus) {
                    $this->bus->dispatch('AllocationPendingReview', [
                        'school_name' => $student['school_name'],
                    ], 'v1');
                }
                return [];
            }
            if ($match['decision'] === 'reject') {
                return [];
            }
            $code = $match['code'];
            $mentors = array_values(array_filter($mentors, function ($m) use ($code) {
                return (int) ($m['school_code'] ?? 0) === (int) $code;
            }));
        }

        // Center
        $mentors = array_values(array_filter($mentors, function ($m) use ($student) {
            return (int) ($m['center'] ?? 0) === (int) ($student['center'] ?? 0);
        }));

        // Target manager
        if (isset($student['manager_id'])) {
            $mentors = array_values(array_filter($mentors, function ($m) use ($student) {
                return (int) ($m['manager_id'] ?? 0) === (int) $student['manager_id'];
            }));
        }

        // Remove mentors explicitly marked with zero capacity
        $mentors = array_values(array_filter($mentors, function ($m) {
            return ($m['capacity'] ?? null) !== 0;
        }));

        return $mentors;
    }

    /**
     * Resolve school name using crosswalk and fuzzy matching.
     *
     * @param array<int,array{name:string,code:int}> $crosswalk
     * @return array{decision:string,code:?int}
     */
    private function resolveSchool(string $name, array $crosswalk): array
    {
        $best = null;
        $bestSim = 0.0;
        foreach ($crosswalk as $row) {
            $sim = Fuzzy::similarity($name, (string) $row['name']);
            if ($sim > $bestSim) {
                $bestSim = $sim;
                $best = $row;
            }
        }
        $decision = Fuzzy::decide($bestSim);
        return ['decision' => $decision, 'code' => $best['code'] ?? null];
    }
}
