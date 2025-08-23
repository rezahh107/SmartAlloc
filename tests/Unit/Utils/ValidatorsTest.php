<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Utils;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Utils\Validators;

final class ValidatorsTest extends BaseTestCase
{
    /** @test */
    public function validates_national_id_checksum(): void
    {
        $this->assertTrue(Validators::nationalIdIr('0000000000'));
        $this->assertFalse(Validators::nationalIdIr('0000000001'));
    }

    /** @test */
    public function validates_mobile(): void
    {
        $this->assertTrue(Validators::mobileIr('09123456789'));
        $this->assertFalse(Validators::mobileIr('9123456789'));
    }

    /** @test */
    public function validates_postal_and_digits16(): void
    {
        $this->assertTrue(Validators::postal10('0123456789'));
        $this->assertFalse(Validators::postal10('12345'));
        $this->assertTrue(Validators::digits16('1234567890123456'));
        $this->assertFalse(Validators::digits16('123'));
    }
}

