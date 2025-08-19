<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Php83;

use SmartAlloc\Tests\BaseTestCase;

class TypedConstants
{
    public const int INT_CONST = 42;
    public const string STR_CONST = 'ok';
}

final class TypedClassConstantsTest extends BaseTestCase
{
    public function test_typed_class_constants_reflection(): void
    {
        if (PHP_VERSION_ID < 80300) {
            self::markTestSkipped('Typed class constants require PHP 8.3');
        }

        $ref = new \ReflectionClass(TypedConstants::class);
        $intConst = $ref->getReflectionConstant('INT_CONST');
        $this->assertSame('int', $intConst->getType()?->getName());
        $this->assertSame(42, $intConst->getValue());

        $strConst = $ref->getReflectionConstant('STR_CONST');
        $this->assertSame('string', $strConst->getType()?->getName());
        $this->assertSame('ok', $strConst->getValue());
    }
}
