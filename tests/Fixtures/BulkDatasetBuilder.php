<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Fixtures;

/**
 * Deterministic bulk dataset builder for performance tests.
 */
final class BulkDatasetBuilder
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function buildStudents(int $count): array
    {
        $students = [];
        for ($i = 1; $i <= $count; $i++) {
            $students[] = [
                'student_id' => $i,
                'gender' => $i % 2 === 0 ? 'f' : 'm',
                'center' => 'c' . ($i % 10),
            ];
        }
        return $students;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function buildMentors(int $count): array
    {
        $mentors = [];
        for ($i = 1; $i <= $count; $i++) {
            $mentors[] = [
                'mentor_id' => $i,
                'gender' => $i % 2 === 0 ? 'f' : 'm',
                'center' => 'c' . ($i % 10),
                'capacity' => 5,
                'assigned' => 0,
            ];
        }
        return $mentors;
    }

    /**
     * Build mentors and students in one call.
     *
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}
     */
    public static function build(int $studentCount, int $mentorCount): array
    {
        return [self::buildMentors($mentorCount), self::buildStudents($studentCount)];
    }
}

