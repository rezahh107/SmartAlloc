<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SmartAlloc\Bootstrap;

if (!function_exists('is_multisite')) {
    function is_multisite(): bool { return false; }
}
if (!function_exists('get_sites')) {
    function get_sites(): array { return []; }
}
if (!function_exists('switch_to_blog')) {
    function switch_to_blog(int $id): void {}
}
if (!function_exists('restore_current_blog')) {
    function restore_current_blog(): void {}
}
if (!function_exists('trailingslashit')) {
    function trailingslashit(string $path): string { return rtrim($path, '/\\') . '/'; }
}
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $dir): void {}
}
if (!function_exists('get_option')) {
    function get_option(string $name, $default = false) { return $default; }
}
if (!function_exists('update_option')) {
    function update_option(string $name, $value): void {}
}
if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(): void {}
}

final class BootstrapActivationTest extends TestCase
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

    public function test_activation_hook_fires_on_single_site(): void
    {
        Functions\expect('do_action')
            ->once()
            ->with('smartalloc_activate', false);

        Bootstrap::activate(false);
    }

    public function test_activation_hook_fires_on_network_wide(): void
    {
        Functions\expect('do_action')
            ->once()
            ->with('smartalloc_activate', true);

        Bootstrap::activate(true);
    }
}

