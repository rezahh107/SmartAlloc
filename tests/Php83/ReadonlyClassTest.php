<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Php83;

use SmartAlloc\Tests\BaseTestCase;

final class ReadonlyClassTest extends BaseTestCase
{
    public function test_readonly_class_reflection(): void
    {
        if (PHP_VERSION_ID < 80200) {
            self::markTestSkipped('readonly classes require PHP 8.2+.');
        }

        eval(<<<'CODE'
        readonly class MyReadonly {
            public int $id;
            public function __construct(int $id) {
                $this->id = $id;
            }
        }
        CODE);

        $ref = new \ReflectionClass('MyReadonly');
        $this->assertTrue($ref->isReadOnly());
        $obj = new \MyReadonly(42);
        $this->assertSame(42, $obj->id);
    }
}
