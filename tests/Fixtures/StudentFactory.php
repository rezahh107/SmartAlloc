<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Fixtures;

final class StudentFactory
{
    private static int $nextId = 1;

    public static function make(array $overrides = []): array
    {
        $id = self::$nextId++;
        $base = [
            'id' => $id,
            'student_id' => $id,
            'gender' => 'M',
            'center' => 1,
            'group_code' => null,
            'manager_id' => 1,
            'school_name' => null,
        ];
        return array_merge($base, $overrides);
    }
}
