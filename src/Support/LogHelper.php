<?php

declare(strict_types=1);

namespace SmartAlloc\Support;

/** Minimal structured error logger to replace direct error_log usage. */
final class LogHelper
{
    public static function error(string $message, array $context = []): void
    {
        $prefix = '[ERR] ' . gmdate('c') . ' ';
        $json   = function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($prefix . $message . ' ' . ($json ?: '')); 
    }
}
