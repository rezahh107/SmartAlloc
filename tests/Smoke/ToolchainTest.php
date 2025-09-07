<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class ToolchainTest extends TestCase
{
    public function test_composer_autoloader_exists(): void
    {
        $this->assertTrue(class_exists(\Composer\Autoload\ClassLoader::class));
    }

    public function test_phpunit_version_is_10(): void
    {
        $this->assertStringStartsWith('10.', \PHPUnit\Runner\Version::id());
    }

    public function test_mockery_is_available(): void
    {
        $this->assertTrue(class_exists('Mockery'));
    }

    public function test_brain_monkey_is_available(): void
    {
        $this->assertTrue(class_exists('Brain\\Monkey\\Container'));
    }

    public function test_smartalloc_namespace_autoloads(): void
    {
        $dir = dirname(__DIR__, 2) . '/includes';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir . '/TestAutoload.php';
        file_put_contents($file, '<?php namespace SmartAlloc; class TestAutoload {}');
        $this->assertTrue(class_exists('SmartAlloc\\TestAutoload'));
        unlink($file);
        @rmdir($dir);
    }

    public function test_php_version_requirement(): void
    {
        $this->assertTrue(version_compare(PHP_VERSION, '8.1.0', '>='));
    }

    public function test_phpcs_is_available(): void
    {
        $phpcs = dirname(__DIR__, 2) . '/vendor/bin/phpcs';
        $this->assertFileExists($phpcs);
        exec($phpcs . ' -i', $out);
        $this->assertStringContainsString('WordPress', implode(' ', $out));
    }

    public function test_configuration_files_exist(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root . '/composer.json');
        $this->assertFileExists($root . '/phpunit.xml.dist');
        $this->assertFileExists($root . '/vendor/autoload.php');
    }
}
