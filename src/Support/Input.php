<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Support;

class Input
{
    public static function get(
        int $type,
        string $name,
        int $filter = FILTER_UNSAFE_RAW,
        array $options = []
    ) {
        $value = filter_input($type, $name, $filter, $options);
        return is_string($value) ? wp_unslash($value) : $value;
    }
}
