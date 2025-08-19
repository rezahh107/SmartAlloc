<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Php83;

use SmartAlloc\Tests\BaseTestCase;

final class JsonValidateTest extends BaseTestCase
{
    public function test_json_validate_function(): void
    {
        if (PHP_VERSION_ID < 80300 || !function_exists('json_validate')) {
            self::markTestSkipped('json_validate() requires PHP 8.3+.');
        }

        $ref = new \ReflectionFunction('json_validate');
        $this->assertTrue($ref->isInternal());

        $valid   = '{"a":1}';
        $invalid = '{"a":,}';

        $this->assertTrue(json_validate($valid));
        $this->assertFalse(json_validate($invalid));
    }
}
