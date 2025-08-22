<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class AutoloadTest extends BaseTestCase
{
    public function test_plugin_classes_autoload(): void
    {
        $this->assertTrue(class_exists(\SmartAlloc\Bootstrap::class));
    }
}
