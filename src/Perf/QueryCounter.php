<?php

declare(strict_types=1);

namespace SmartAlloc\Perf;

/**
 * Query counter utility backed by global $wpdb->queries.
 */
final class QueryCounter
{
    public static function isAvailable(): bool
    {
        global $wpdb;
        return is_object($wpdb) && is_array($wpdb->queries ?? null);
    }

    public static function reset(): void
    {
        if (self::isAvailable()) {
            $GLOBALS['wpdb']->queries = [];
        }
    }

    public static function lastCount(): int
    {
        return self::isAvailable() ? count($GLOBALS['wpdb']->queries) : 0;
    }

    // Backwards compatibility for previous start/stop API
    public function start(): void
    {
        self::reset();
    }

    public function stop(): int
    {
        return self::lastCount();
    }
}
