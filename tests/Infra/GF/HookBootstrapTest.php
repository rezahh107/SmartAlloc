<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Infra\GF;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Infra\GF\HookBootstrap;
use SmartAlloc\Tests\BaseTestCase;

final class HookBootstrapTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function prepare($sql, ...$args) {
                return vsprintf(str_replace('%s', '%s', $sql), $args);
            }
            public function get_results($sql, $output = OBJECT) { return [['form_id' => 150]]; }
        };
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_registers_hooks_for_enabled_forms(): void
    {
        Functions\expect('add_action')->once()->with('gform_after_submission_150', [\SmartAlloc\Infra\GF\SabtSubmissionHandler::class, 'handle'], 10, 2);
        HookBootstrap::registerEnabledForms();
    }
}
