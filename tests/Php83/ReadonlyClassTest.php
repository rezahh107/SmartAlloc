<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Php83;

use SmartAlloc\Tests\BaseTestCase;

readonly class ReadonlyUser
{
    public function __construct(public string $name)
    {
    }
}

final class ReadonlyClassTest extends BaseTestCase
{
    public function test_readonly_class_properties_cannot_change(): void
    {
        if (PHP_VERSION_ID < 80200) {
            self::markTestSkipped('Readonly classes require PHP 8.2');
        }

        $u = new ReadonlyUser('alice');
        $this->assertSame('alice', $u->name);

        try {
            $u->name = 'bob';
            $this->fail('Expected error when modifying readonly property');
        } catch (\Error $e) {
            $this->assertStringContainsString('readonly property', $e->getMessage());
        }
    }
}
