<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Php83;

use SmartAlloc\Tests\BaseTestCase;

final class TypedClassConstantsTest extends BaseTestCase
{
    public function test_typed_class_constant_has_type(): void
    {
        if (PHP_VERSION_ID < 80300) {
            self::markTestSkipped('Typed class constants require PHP 8.3+.');
        }

        eval(<<<'CODE'
        class TypedConstants {
            public const string NAME = 'sa';
        }
        CODE);

        $ref   = new \ReflectionClass('TypedConstants');
        $const = $ref->getReflectionConstant('NAME');
        $this->assertSame('string', $const->getType()->getName());
        $this->assertSame('sa', $const->getValue());
    }
}
