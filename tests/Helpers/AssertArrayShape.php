<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Helpers;

use PHPUnit\Framework\Assert;

/**
 * Simple assertion helper for array shapes.
 */
trait AssertArrayShape
{
    /**
     * Assert that array has expected keys and value types.
     *
     * @param array<string,string> $shape key => type
     * @param array<string,mixed> $actual
     */
    public static function assertArrayShape(array $shape, array $actual): void
    {
        foreach ($shape as $key => $type) {
            Assert::assertArrayHasKey($key, $actual, "Missing key '{$key}'");
            $value = $actual[$key];
            switch ($type) {
                case 'int':
                    Assert::assertIsInt($value, "Key '{$key}' is not int");
                    break;
                case 'bool':
                    Assert::assertIsBool($value, "Key '{$key}' is not bool");
                    break;
                case 'array':
                    Assert::assertIsArray($value, "Key '{$key}' is not array");
                    break;
                case 'string':
                    Assert::assertIsString($value, "Key '{$key}' is not string");
                    break;
                case 'float':
                    Assert::assertIsFloat($value, "Key '{$key}' is not float");
                    break;
                default:
                    Assert::fail("Unsupported type assertion '{$type}' for key '{$key}'");
            }
        }
    }
}
