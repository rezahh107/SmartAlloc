<?php

declare(strict_types=1);

namespace SmartAlloc\Compat\ThirdParty;

use SmartAlloc\Compat\DateFilters;

/**
 * Bypass Jalali date filters when exporting machine-readable data.
 */
final class JalaliDateConverter
{
    /**
     * Detect if a Jalali date filter is registered on 'date_i18n'.
     */
    public static function hasJalaliFilter(): bool
    {
        return self::callbacks() !== [];
    }

    /**
     * Run the given callable with Jalali date filters temporarily removed.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function withDateI18nBypassed(callable $fn)
    {
        $callbacks = self::callbacks();
        if ($callbacks === []) {
            return $fn();
        }
        $original = $GLOBALS['wp_filter']['date_i18n'] ?? null;
        DateFilters::remove('date_i18n', $callbacks);
        try {
            unset($GLOBALS['wp_filter']['date_i18n']);
            return $fn();
        } finally {
            if ($original !== null) {
                $GLOBALS['wp_filter']['date_i18n'] = $original;
            }
            DateFilters::restore('date_i18n', $callbacks);
        }
    }

    /**
     * @return array<int, array{callback: callable, priority: int, accepted_args: int}>
     */
    private static function callbacks(): array
    {
        return DateFilters::find('date_i18n', static function ($cb): bool {
            if (!is_array($cb) || !isset($cb[0], $cb[1])) {
                return false;
            }
            if (!is_string($cb[1])) {
                return false;
            }
            $class = is_object($cb[0]) ? get_class($cb[0]) : $cb[0];
            if (!is_string($class)) {
                return false;
            }
            return str_contains($class, 'Jalali') || str_contains($class, 'JDC');
        });
    }
}
