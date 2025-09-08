<?php

declare(strict_types=1);

namespace SmartAlloc\Core;

/**
 * Security utilities
 */
class Security
{
    public static function verifyCaps(string $capability = 'manage_options'): bool
    {
        return current_user_can($capability);
    }

    public static function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action) !== false;
    }

    public static function sanitizeInput(string $input): string
    {
        return sanitize_text_field($input);
    }

    public static function escapeOutput(string $output): string
    {
        return esc_html($output);
    }
}
