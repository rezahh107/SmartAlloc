<?php

declare(strict_types=1);

namespace SmartAlloc\Domain\Allocation;

final class AllocationStatus
{
    public const AUTO = 'auto';
    public const MANUAL = 'manual';
    public const REJECT = 'reject';

    /**
     * Validate allocation status value
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, [self::AUTO, self::MANUAL, self::REJECT], true);
    }
}
