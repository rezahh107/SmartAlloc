<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class PersianRtlTest extends BaseTestCase
{
    public function test_persian_digits_rendering_and_not_corrupted_in_exports(): void
    {
        self::markTestSkipped('TODO: Implement export flow and assert Persian digits remain intact.');
    }

    public function test_arabic_vs_persian_characters_not_mangled(): void
    {
        self::markTestSkipped('TODO: Verify distinct Arabic/Persian characters persist through processing.');
    }

    public function test_jalali_roundtrip_in_csv_xlsx_or_skip(): void
    {
        self::markTestSkipped('TODO: Add Jalali date handling and round-trip check in CSV/XLSX.');
    }
}
