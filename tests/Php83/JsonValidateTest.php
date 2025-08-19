<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Php83;

use SmartAlloc\Tests\BaseTestCase;

final class JsonValidateTest extends BaseTestCase
{
    public function test_json_validate(): void
    {
        if (PHP_VERSION_ID < 80300) {
            self::markTestSkipped('json_validate() requires PHP 8.3');
        }

        $valid = '{"a":1,"b":[2,3]}';
        $invalid = '{"a":1';
        $this->assertTrue(json_validate($valid));
        $this->assertFalse(json_validate($invalid));
    }
}
