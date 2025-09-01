<?php
declare(strict_types=1);

namespace SmartAlloc\Security;

/**
 * Sanitize and redact server input values.
 */
final class InputRedactor
{
    /** @var array<int,string> */
    private const SENSITIVE_KEYS = [
        'HTTP_AUTHORIZATION',
        'PHP_AUTH_PW',
        'HTTP_COOKIE',
    ];

    /**
     * @param mixed $value
     */
    public static function sanitizeServerVar(string $key, $value): string
    {
        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return '[REDACTED]';
        }
        $raw = \function_exists('sanitize_text_field')
            ? \sanitize_text_field((string) $value)
            : (string) $value;
        if (\function_exists('wp_strip_all_tags')) {
            $raw = \wp_strip_all_tags($raw);
        } else {
            $raw = strip_tags($raw); // phpcs:ignore WordPressVIPMinimum.Functions.StripTags.StripTagsOneParameter
        }
        return trim($raw);
    }

    /**
     * @param array<string,mixed> $server
     * @return array<string,string>
     */
    public static function sanitizeServerArray(array $server): array
    {
        $sanitized = [];
        foreach ($server as $k => $v) {
            $sanitized[$k] = self::sanitizeServerVar((string) $k, $v);
        }
        return $sanitized;
    }
}
