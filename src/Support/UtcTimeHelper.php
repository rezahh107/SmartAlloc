<?php

declare(strict_types=1);

namespace SmartAlloc\Support;

class UtcTimeHelper
{
    public static function getCurrentUtcTimestamp(): int
    {
        return function_exists('current_time') ? (int) current_time('timestamp', true) : time();
    }

    public static function getCurrentUtcDatetime(string $format = 'Y-m-d H:i:s'): string
    {
        if (function_exists('current_time')) {
            return current_time($format === 'Y-m-d H:i:s' ? 'mysql' : $format, true);
        }
        return gmdate($format);
    }

    public static function timestampToUtcDatetime(int $timestamp, string $format = 'Y-m-d H:i:s'): string
    {
        return gmdate($format, $timestamp);
    }

    public static function utcDatetimeToTimestamp(string $datetime): int
    {
        return strtotime($datetime . ' UTC');
    }

    public static function getTimezoneOffset(): int
    {
        if (function_exists('wp_timezone')) {
            $tz = wp_timezone();
            return $tz->getOffset(new \DateTime('now', new \DateTimeZone('UTC')));
        }
        if (function_exists('get_option')) {
            return (int) get_option('gmt_offset', 0) * 3600;
        }
        return 0;
    }

    public static function utcToLocal(string $utcDatetime, string $format = 'Y-m-d H:i:s'): string
    {
        $timestamp = self::utcDatetimeToTimestamp($utcDatetime) + self::getTimezoneOffset();
        return gmdate($format, $timestamp);
    }

    public static function localToUtc(string $localDatetime, string $format = 'Y-m-d H:i:s'): string
    {
        $timestamp = strtotime($localDatetime) - self::getTimezoneOffset();
        return gmdate($format, $timestamp);
    }

    public static function isValidUtcDatetime(string $datetime): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime, new \DateTimeZone('UTC'));
        return $parsed && $parsed->format('Y-m-d H:i:s') === $datetime;
    }
}
