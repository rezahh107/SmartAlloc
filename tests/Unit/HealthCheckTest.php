<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit;

use PHPUnit\Framework\TestCase;

class HealthCheckTest extends TestCase
{
    public function testPhpVersionCompatibility(): void
    {
        $this->assertTrue(
            version_compare(PHP_VERSION, '7.4', '>='),
            'PHP version must be 7.4 or higher'
        );
    }

    public function testRequiredExtensions(): void
    {
        $required = ['json', 'mbstring', 'curl'];

        foreach ($required as $extension) {
            $this->assertTrue(
                extension_loaded($extension),
                "Required extension not loaded: {$extension}"
            );
        }
    }

    public function testAutoloaderFunctionality(): void
    {
        $this->assertTrue(
            class_exists('Composer\\Autoload\\ClassLoader'),
            'Composer autoloader not functioning'
        );
    }

    public function testFilePermissions(): void
    {
        $testFile = sys_get_temp_dir() . '/smartalloc_test_' . uniqid();

        $this->assertTrue(
            file_put_contents($testFile, 'test') !== false,
            'Cannot write to temporary directory'
        );

        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }
}
