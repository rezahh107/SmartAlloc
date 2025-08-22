<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;

final class DomainLoadTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_domain_load_and_translation(): void
    {
        Functions\expect('load_plugin_textdomain')->andReturn(true);
        $this->assertTrue(load_plugin_textdomain('smartalloc'));
        Functions\when('__')->alias(function ($text, $domain) {
            return ($domain === 'smartalloc' && $text === 'Hello %s') ? 'سلام %s' : $text;
        });
        $this->assertSame('سلام %s', __('Hello %s', 'smartalloc'));
    }
}
