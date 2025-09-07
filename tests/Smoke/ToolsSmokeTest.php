<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class ToolsSmokeTest extends TestCase
{
    /**
     * @group smoke
     */
    public function test_toolchain_is_available(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertTrue(class_exists(\Composer\Autoload\ClassLoader::class));
        $this->assertFileExists($root . '/vendor/bin/phpcs');
        $this->assertFileExists($root . '/vendor/bin/phpunit');
    }
}
