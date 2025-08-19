<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Php83;

use SmartAlloc\Tests\BaseTestCase;

class OverrideBase
{
    public function run(): string
    {
        return 'base';
    }
}

class OverrideChild extends OverrideBase
{
    #[\Override]
    public function run(): string
    {
        return 'child';
    }
}

final class OverrideAttributeTest extends BaseTestCase
{
    public function test_override_attribute(): void
    {
        if (PHP_VERSION_ID < 80300) {
            self::markTestSkipped('Override attribute requires PHP 8.3');
        }

        $ref = new \ReflectionMethod(OverrideChild::class, 'run');
        $attrs = $ref->getAttributes(\Override::class);
        $this->assertCount(1, $attrs, '#[Override] attribute present');
        $this->assertSame('child', (new OverrideChild())->run());
    }
}
