<?php

declare(strict_types=1);

namespace SmartAlloc\Tests;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Integration\GravityForms\Form150;

final class Form150ValidationTest extends TestCase
{
    public function testNationalCodeAlgorithm(): void
    {
        $validator = new Form150();
        $method    = new \ReflectionMethod($validator, 'isValidNationalCode');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($validator, '0123456789'));
        $this->assertFalse($method->invoke($validator, '0123456780'));
        $this->assertFalse($method->invoke($validator, '1111111111'));
    }
}
