<?php

declare(strict_types=1);

namespace SmartAlloc\Domain\Allocation;

/**
 * Value object returned from allocation operations.
 *
 * @phpcs:ignoreFile
 */
final class AllocationResult
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(private array $data)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function to_array(): array
    {
        return $this->data;
    }
}
