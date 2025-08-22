<?php

declare(strict_types=1);

namespace SmartAlloc\Testing;

/**
 * Helper to set/reset fault flags via WordPress filters in tests.
 */
final class TestFilters
{
    private const TAG = 'smartalloc/test/faults';
    /** @var callable|null */
    private static $cb = null;

    /**
     * @param array<string,mixed> $flags
     */
    public static function set(array $flags): void
    {
        self::reset();
        self::$cb = function(array $f) use ($flags): array {
            return array_merge($f, $flags);
        };
        if (function_exists('add_filter')) {
            add_filter(self::TAG, self::$cb, 10, 1);
        }
        $GLOBALS['filters'][self::TAG] = self::$cb;
    }

    public static function reset(): void
    {
        if (self::$cb !== null && function_exists('remove_filter')) {
            remove_filter(self::TAG, self::$cb, 10);
        }
        unset($GLOBALS['filters'][self::TAG]);
        self::$cb = null;
    }
}
