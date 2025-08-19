<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use SmartAlloc\Cli\DebugCommand;
use SmartAlloc\Tests\BaseTestCase;

final class DebugCommandTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('current_user_can')->justReturn(true);
        $GLOBALS['wp_upload_dir_basedir'] = sys_get_temp_dir();
        Functions\when('get_bloginfo')->alias(fn() => '6.0');
        if (!function_exists('\\WP_CLI\\Utils\\get_flag_value')) {
            Functions\when('\\WP_CLI\\Utils\\get_flag_value')->alias(fn($assoc, $key, $default = null) => $assoc[$key] ?? $default);
        }
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        $entry = ['message' => 'oops', 'file' => 'file.php', 'line' => 1];
        $GLOBALS['sa_options'] = ['smartalloc_debug_errors' => [$entry]];
    }

    protected function tearDown(): void
    {
        $GLOBALS['sa_options'] = [];
        unset($GLOBALS['wp_upload_dir_basedir']);
        parent::tearDown();
    }

    public function test_creates_bundle(): void
    {
        if (!class_exists('WP_CLI')) {
            $this->markTestSkipped('WP-CLI not present');
        }
        $finger = md5('oopsfile.php1');
        $cmd = new DebugCommand();
        ob_start();
        $code = $cmd([], ['id' => $finger]);
        $out = trim(ob_get_clean());
        $this->assertSame(0, $code);
        $this->assertFileExists($out);
    }
}
