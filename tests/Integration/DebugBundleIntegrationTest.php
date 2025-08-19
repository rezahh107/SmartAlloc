<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Admin\DebugScreen;
use SmartAlloc\Debug\ErrorStore;
use SmartAlloc\Tests\BaseTestCase;

final class DebugBundleIntegrationTest extends BaseTestCase
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
        Functions\when('current_user_can')->alias(fn($c) => $c === 'manage_smartalloc');
        Functions\when('wp_verify_nonce')->alias(fn($n,$a) => $n === 'good' && $a === 'smartalloc_debug_bundle');
        Functions\when('wp_create_nonce')->alias(fn($a) => 'good');
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_html')->alias(fn($v) => $v);
        Functions\when('esc_attr')->alias(fn($v) => $v);
        Functions\when('wp_die')->alias(fn($m) => throw new \RuntimeException($m));
        $entry = ['message' => 'oops', 'file' => 'file.php', 'line' => 1];
        $GLOBALS['sa_options'] = ['smartalloc_debug_errors' => [$entry], 'smartalloc_debug_enabled' => true];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $GLOBALS['sa_options'] = [];
        unset($GLOBALS['wp_upload_dir_basedir']);
        unset($_GET['bundle'], $_REQUEST['_wpnonce']);
        parent::tearDown();
    }

    public function test_downloads_bundle(): void
    {
        $_GET['bundle'] = md5('oopsfile.php1');
        $_REQUEST['_wpnonce'] = 'good';
        ob_start();
        DebugScreen::render();
        $out = ob_get_clean();
        $this->assertStringStartsWith('PK', $out);
    }

    public function test_nonce_and_capability_required(): void
    {
        $_GET['bundle'] = md5('oopsfile.php1');
        $_REQUEST['_wpnonce'] = 'bad';
        ob_start();
        $this->expectException(\RuntimeException::class);
        try {
            DebugScreen::render();
        } finally {
            ob_end_clean();
        }
        Functions\when('current_user_can')->alias(fn($c) => false);
        $_REQUEST['_wpnonce'] = 'good';
        ob_start();
        $this->expectException(\RuntimeException::class);
        try {
            DebugScreen::render();
        } finally {
            ob_end_clean();
        }
    }
}
