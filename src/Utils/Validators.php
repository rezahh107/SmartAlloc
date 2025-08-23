<?php

declare(strict_types=1);

namespace SmartAlloc\Utils;

final class Validators
{
    public static function nationalIdIr(string $id): bool
    {
        if (!preg_match('/^\d{10}$/', $id)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((10 - $i) * (int) $id[$i]);
        }
        $rem = $sum % 11;
        $check = (int) $id[9];
        if ($rem < 2) {
            return $check === $rem;
        }
        return $check === 11 - $rem;
    }

    public static function mobileIr(string $mobile): bool
    {
        return (bool) preg_match('/^09\d{9}$/', $mobile);
    }

    public static function postal10(string $postal): bool
    {
        return (bool) preg_match('/^\d{10}$/', $postal);
    }

    public static function digits16(string $value): bool
    {
        return (bool) preg_match('/^\d{16}$/', $value);
    }
}
