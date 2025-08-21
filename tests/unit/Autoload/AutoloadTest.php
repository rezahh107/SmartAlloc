<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AutoloadTest extends TestCase
{
    public function test_plugin_classes_autoload(): void
    {
        $this->assertTrue(class_exists(\SmartAlloc\Bootstrap::class));
    }
}
