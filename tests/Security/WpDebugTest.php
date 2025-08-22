<?php

use SmartAlloc\Tests\BaseTestCase;

class WpDebugTest extends BaseTestCase
{
    public function test_wp_debug_and_display_errors(): void
    {
        $this->assertTrue(defined('WP_DEBUG') && WP_DEBUG);
        $this->assertSame('0', ini_get('display_errors'));

        $this->expectException(\ErrorException::class);
        trigger_error('Notice for testing', E_USER_NOTICE);
    }
}
