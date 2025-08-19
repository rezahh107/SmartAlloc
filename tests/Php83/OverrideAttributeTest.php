<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Php83;

use SmartAlloc\Tests\BaseTestCase;

final class OverrideAttributeTest extends BaseTestCase
{
    public function test_override_attribute_detected(): void
    {
        if (PHP_VERSION_ID < 80300) {
            self::markTestSkipped('#[\\Override] requires PHP 8.3+.');
        }

        eval(<<<'CODE'
        class OverrideBase {
            public function foo(): int {
                return 1;
            }
        }

        class OverrideChild extends OverrideBase {
            #[\Override]
            public function foo(): int {
                return 2;
            }
        }
        CODE);

        $ref = new \ReflectionMethod('OverrideChild', 'foo');
        $this->assertCount(1, $ref->getAttributes(\Override::class));
    }
}
