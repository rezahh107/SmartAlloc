<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Debug;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Debug\ReproBuilder;
use SmartAlloc\Debug\ErrorStore;
use SmartAlloc\Tests\BaseTestCase;

final class ReproBuilderTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        $GLOBALS['wp_upload_dir_basedir'] = sys_get_temp_dir();
        Functions\when('get_bloginfo')->alias(fn() => '6.0');
        Functions\when('wp_parse_url')->alias(fn($v) => parse_url($v));
        $entry = ['message' => 'oops', 'file' => 'file.php', 'line' => 1, 'context' => ['route' => '/wp-json/foo', 'method' => 'POST']];
        $GLOBALS['sa_options'] = ['smartalloc_debug_errors' => [$entry]];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $GLOBALS['sa_options'] = [];
        unset($GLOBALS['wp_upload_dir_basedir']);
        parent::tearDown();
    }

    public function test_scaffold_and_bundle(): void
    {
        $finger = md5('oopsfile.php1');
        $builder = new ReproBuilder();
        $paths = $builder->buildScaffold($finger);
        $this->assertFileExists($paths['test']);
        $this->assertFileExists($paths['blueprint']);
        $class = 'SmartAlloc\\Tests\\Debug\\Repro\\Repro' . $finger . 'Test';
        if (!class_exists($class, false)) {
            require $paths['test'];
        }
        $this->assertTrue(class_exists($class));
        $obj = new $class('testRepro');
        try {
            $obj->testRepro();
            $this->fail('Expected skip');
        } catch (\Throwable $t) {
            $this->assertStringContainsString('Repro scaffold', $t->getMessage());
        }
        $data = json_decode((string) file_get_contents($paths['blueprint']), true);
        $this->assertIsArray($data);
        unlink($paths['test']);
        unlink($paths['blueprint']);
    }
}
