<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Php83;

use SmartAlloc\Tests\BaseTestCase;

class DynamicConst
{
    public const VALUE = 'dynamic';
}

final class DynamicConstFetchTest extends BaseTestCase
{
    public function test_dynamic_class_constant_fetch(): void
    {
        if (PHP_VERSION_ID < 80300) {
            self::markTestSkipped('Dynamic class-constant fetch requires PHP 8.3');
        }

        $const = 'VALUE';
        $class = DynamicConst::class;
        $this->assertSame('dynamic', $class::{$const});
    }
}
