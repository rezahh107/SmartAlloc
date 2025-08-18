<?php

declare(strict_types=1);

namespace SmartAlloc\Domain\Allocation;

use InvalidArgumentException;

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

        return new AllocationResult([
            'allocated' => true,
            'student_id' => $id,
        ]);
    }
}
