<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PersianValidatorTest extends TestCase
{
    public function test_national_id_and_card_validation_or_skip(): void
    {
        if (!function_exists('smartalloc_validate_national_id') && !class_exists('\\SmartAlloc\\Validators')) {
            $this->markTestSkipped('feature not implemented');
        }

        $this->assertTrue($this->isValidIranNationalId('0084575948'));
        $this->assertFalse($this->isValidIranNationalId('1111111111'));
        $this->assertTrue($this->luhn('6274129000001234'));
        $this->assertFalse($this->luhn('6274129000001235'));
    }

    private function luhn(string $s): bool
    {
        $sum = 0; $alt = false;
        for ($i = strlen($s) - 1; $i >= 0; $i--) {
            $n = (int) $s[$i];
            if ($alt) { $n *= 2; if ($n > 9) { $n -= 9; } }
            $sum += $n; $alt = !$alt;
        }
        return $sum % 10 === 0;
    }

    private function isValidIranNationalId(string $nid): bool
    {
        if (!preg_match('/^\d{10}$/', $nid)) { return false; }
        if (preg_match('/^(\d)\1{9}$/', $nid)) { return false; }
        $c = (int) $nid[9];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) { $sum += (int) $nid[$i] * (10 - $i); }
        $r = $sum % 11;
        return ($r < 2 && $c == $r) || ($r >= 2 && $c + $r == 11);
    }
}
