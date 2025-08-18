<?php

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

