<?php

declare(strict_types=1);

namespace SmartAlloc\Core;

/**
 * Input validation utilities
 */
class Validator
{
    public static function validateEmail(string $email): bool
    {
        return is_email($email) !== false;
    }

    public static function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function validateInt(mixed $value, int $min = 0, int $max = PHP_INT_MAX): bool
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        return $int !== false && $int >= $min && $int <= $max;
    }
}
