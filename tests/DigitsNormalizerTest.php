<?php

declare(strict_types=1);

namespace SmartAlloc\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Test for digit normalization
 */
class DigitsNormalizerTest extends TestCase
{
    /**
     * Test Persian digit normalization
     */
    public function testPersianDigits(): void
    {
        $normalizer = new \SmartAlloc\Integration\GravityForms(new \SmartAlloc\Container());
        
        $persian = '۰۱۲۳۴۵۶۷۸۹';
        $expected = '0123456789';
        
        $this->assertEquals($expected, $this->callNormalizeDigits($normalizer, $persian));
    }

    /**
     * Test Arabic digit normalization
     */
    public function testArabicDigits(): void
    {
        $normalizer = new \SmartAlloc\Integration\GravityForms(new \SmartAlloc\Container());
        
        $arabic = '٠١٢٣٤٥٦٧٨٩';
        $expected = '0123456789';
        
        $this->assertEquals($expected, $this->callNormalizeDigits($normalizer, $arabic));
    }

    /**
     * Test mixed digits
     */
    public function testMixedDigits(): void
    {
        $normalizer = new \SmartAlloc\Integration\GravityForms(new \SmartAlloc\Container());
        
        $mixed = '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩';
        $expected = '01234567890123456789';
        
        $this->assertEquals($expected, $this->callNormalizeDigits($normalizer, $mixed));
    }

    /**
     * Test with non-digit characters
     */
    public function testWithNonDigits(): void
    {
        $normalizer = new \SmartAlloc\Integration\GravityForms(new \SmartAlloc\Container());
        
        $input = 'کد: ۰۱۲۳۴۵۶۷۸۹';
        $expected = '0123456789';
        
        $this->assertEquals($expected, $this->callNormalizeDigits($normalizer, $input));
    }

    /**
     * Call the private normalizeDigits method using reflection
     */
    private function callNormalizeDigits($object, string $value): string
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod('normalizeDigits');
        $method->setAccessible(true);
        
        return $method->invoke($object, $value);
    }
} 