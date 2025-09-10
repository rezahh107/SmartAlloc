<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace {
    if (!function_exists('wp_create_nonce')) {
        function wp_create_nonce($action = -1)
        {
            return substr(md5($action . 'smartalloc'), -12);
        }
    }

    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($nonce, $action = -1)
        {
            return $nonce === wp_create_nonce($action);
        }
    }
}
