<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Infra\GF\SabtEntryMapper;

final class SabtEntryMapperTest extends TestCase
{
    private SabtEntryMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SabtEntryMapper();
    }

    public function testInvalidMobileReturnsError(): void
    {
        $entry = ['20' => '0912345678'];
        $result = $this->mapper->mapEntry($entry);
        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_mobile', $result['code']);
    }

    public function testInvalidTrackingCodeReturnsError(): void
    {
        $entry = [
            '20' => '09123456789',
            '76' => '1111111111111111'
        ];
        $result = $this->mapper->mapEntry($entry);
        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_tracking', $result['code']);
    }

    public function testDuplicateContactNumbersReturnError(): void
    {
        $entry = [
            '20' => '09123456789',
            '22' => '09123456789',
        ];
        $result = $this->mapper->mapEntry($entry);
        $this->assertFalse($result['ok']);
        $this->assertSame('duplicate_phone', $result['code']);
    }

    public function testAliasPostalCodeOverridesOriginal(): void
    {
        $entry = [
            '20' => '09123456789',
            '76' => '1234567890123456',
            'postal_code' => '9999999999',
            'postal_code_alias' => '12345',
        ];
        $result = $this->mapper->mapEntry($entry);
        $this->assertTrue($result['ok']);
        $this->assertSame('12345', $result['student']['postal_code']);
    }

    public function testNormalizationAndDefaults(): void
    {
        $entry = [
            '92' => ' M ',
            '20' => '۰۹۱۲۳۴۵۶۷۸۹',
            '22' => '',
            '76' => '1234567890123456',
            'postal_code' => '۱۲۳۴۵۶۷۸۹۰',
        ];
        $result = $this->mapper->mapEntry($entry);
        $this->assertTrue($result['ok']);
        $student = $result['student'];
        $this->assertSame('M', $student['gender']);
        $this->assertSame('09123456789', $student['mobile']);
        $this->assertSame('00000000000', $student['phone']);
        $this->assertSame('1234567890', $student['postal_code']);
    }
}
