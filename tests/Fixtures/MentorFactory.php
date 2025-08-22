<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Fixtures;

final class MentorFactory
{
    private static int $nextId = 1;

    /**
     * Create mentor fixture.
     */
    public static function make(
        string $gender,
        int $center,
        int $managerId,
        ?string $groupCode = null,
        ?int $capacity = null,
        ?int $assigned = null,
        ?int $allocationsNew = null,
        ?int $schoolCode = null
    ): array {
        $id = self::$nextId++;
        return [
            'mentor_id' => $id,
            'gender' => $gender,
            'center' => $center,
            'manager_id' => $managerId,
            'group_code' => $groupCode,
            'capacity' => $capacity,
            'assigned' => $assigned ?? 0,
            'allocations_new' => $allocationsNew ?? 0,
            'school_code' => $schoolCode,
        ];
    }
}
