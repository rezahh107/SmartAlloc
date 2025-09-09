<?php

declare(strict_types=1);

namespace SmartAlloc\Runtime;

final class UtcRuntime
{
    public static function isUtc(): bool
    {
        return date_default_timezone_get() === 'UTC';
    }
}
