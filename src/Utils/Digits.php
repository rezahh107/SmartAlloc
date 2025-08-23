<?php

declare(strict_types=1);

namespace SmartAlloc\Utils;

final class Digits
{
    public static function fa2en(string $input): string
    {
        $map = [
            '۰' => '0','١' => '1','۱' => '1','٢' => '2','۲' => '2','٣' => '3','۳' => '3','٤' => '4','۴' => '4','٥' => '5','۵' => '5','٦' => '6','۶' => '6','٧' => '7','۷' => '7','٨' => '8','۸' => '8','٩' => '9','۹' => '9',
        ];
        return strtr($input, $map);
    }

    public static function stripNonDigits(string $input): string
    {
        return preg_replace('/\D+/', '', $input) ?: '';
    }
}
