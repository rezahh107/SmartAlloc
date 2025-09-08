<?php

declare(strict_types=1);

namespace SmartAlloc\Core;

/**
 * Simple caching utilities
 */
class Cache
{
    public static function get(string $key): mixed
    {
        return get_transient('smartalloc_' . $key);
    }

    public static function set(string $key, mixed $value, int $expiration = 3600): bool
    {
        return set_transient('smartalloc_' . $key, $value, $expiration);
    }

    public static function delete(string $key): bool
    {
        return delete_transient('smartalloc_' . $key);
    }
}
