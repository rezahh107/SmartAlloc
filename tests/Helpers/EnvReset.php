<?php

use Brain\Monkey\Functions;

if (!function_exists('sa_test_freeze_time')) {
    function sa_test_freeze_time(int $ts): void {
        Functions\when('time')->justReturn($ts);
        Functions\when('current_time')->alias(function (string $type = 'mysql') use ($ts) {
            if ($type === 'timestamp') {
                return $ts;
            }
            $format = $type === 'mysql' ? 'Y-m-d H:i:s' : $type;
            return gmdate($format, $ts);
        });
    }
}

if (!function_exists('sa_test_unfreeze_time')) {
    function sa_test_unfreeze_time(): void {
        Functions\when('time')->passThrough();
        Functions\when('current_time')->passThrough();
    }
}

if (!function_exists('sa_test_seed_rng')) {
    function sa_test_seed_rng(int $seed = 1337): void {
        mt_srand($seed);
        srand($seed);
    }
}

if (!function_exists('sa_test_flush_cache')) {
    function sa_test_flush_cache(): void {
        if (function_exists('sa_cache_flush')) {
            sa_cache_flush();
            return;
        }
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}

if (!function_exists('sa_test_reset_counters')) {
    function sa_test_reset_counters(): void {
        global $wpdb;
        if (!isset($wpdb) || !method_exists($wpdb, 'query') || !method_exists($wpdb, 'prepare')) {
            return;
        }
        $table = $wpdb->prefix . 'smartalloc_counters';
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE %d = %d", 1, 1));
    }
}

if (!function_exists('sa_test_clear_options')) {
    function sa_test_clear_options(array $keys): void {
        if (!function_exists('delete_option')) {
            return;
        }
        foreach ($keys as $key) {
            delete_option($key);
        }
    }
}

