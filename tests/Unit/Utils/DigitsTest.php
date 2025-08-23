<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Utils;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Utils\Digits;

final class DigitsTest extends BaseTestCase
{
    /** @test */
    public function it_converts_persian_digits_to_ascii(): void
    {
        $this->assertSame('0123456789', Digits::fa2en('۰۱۲۳۴۵۶۷۸۹'));
    }

    /** @test */
    public function it_strips_non_digits(): void
    {
        $this->assertSame('123', Digits::stripNonDigits('a1b2c3'));
    }
}

