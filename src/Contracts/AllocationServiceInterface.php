<?php
declare(strict_types=1);

namespace SmartAlloc\Contracts;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Services\Exceptions\DuplicateAllocationException;
use SmartAlloc\Services\Exceptions\InsufficientCapacityException;
use SmartAlloc\Services\Exceptions\InvalidFormContextException;

interface AllocationServiceInterface
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     * @throws DuplicateAllocationException
     * @throws InsufficientCapacityException
     * @throws InvalidFormContextException
     */
    public function allocateWithContext(FormContext $ctx, array $payload): array;

    /**
     * Legacy entrypoint: delegates to form 150.
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function allocate(array $payload): array;
}
