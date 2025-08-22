<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Infra;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Infra\GF\HookBootstrap;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class HookBootstrapTest extends BaseTestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function get_results($sql, $output = ARRAY_A) { return [['form_id'=>150]]; }
        };
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_registers_gf_hooks_for_enabled_forms(): void {
        Functions\expect('add_action')
            ->once()
            ->with('gform_after_submission_150', [\SmartAlloc\Infra\GF\SabtSubmissionHandler::class, 'handle'], 10, 2);
        HookBootstrap::registerEnabledForms();
    }
}
