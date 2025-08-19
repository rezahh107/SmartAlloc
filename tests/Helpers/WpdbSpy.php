<?php

namespace {
    if (!function_exists('sa_wpdb_spy_start')) {
        function sa_wpdb_spy_start(): void {
            global $wpdb;
            $GLOBALS['_sa_wpdb_start'] = isset($wpdb->queries) ? count($wpdb->queries) : 0;
        }
    }

    if (!function_exists('sa_wpdb_spy_stop')) {
        function sa_wpdb_spy_stop(): int {
            global $wpdb;
            $start = $GLOBALS['_sa_wpdb_start'] ?? 0;
            $end = isset($wpdb->queries) ? count($wpdb->queries) : 0;
            return max(0, $end - $start);
        }
    }

    if (!function_exists('sa_wpdb_spy_queries')) {
        function sa_wpdb_spy_queries(): array {
            global $wpdb;
            $start = $GLOBALS['_sa_wpdb_start'] ?? 0;
            $all = $wpdb->queries ?? [];
            return array_slice($all, $start);
        }
    }
}

namespace SmartAlloc\Tests\Helpers {
    final class WpdbSpy
    {
        public static function start(): void
        {
            \sa_wpdb_spy_start();
        }

        public static function stop(): int
        {
            return \sa_wpdb_spy_stop();
        }

        public static function count(callable $cb): int
        {
            self::start();
            $cb();
            return self::stop();
        }
    }
}

