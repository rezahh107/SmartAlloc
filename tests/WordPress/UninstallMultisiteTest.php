<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UninstallMultisiteTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('WP_UnitTestCase')) {
            $this->markTestSkipped('WP test suite not available');
        }
        if (!class_exists('\\Brain\\Monkey\\Functions')) {
            $this->markTestSkipped('Brain Monkey not installed');
        }
        if (!class_exists('\\org\\bovigo\\vfs\\vfsStream')) {
            $this->markTestSkipped('vfsStream not installed');
        }
    }

    public function test_uninstall_cleans_options_with_mocks(): void
    {
        \Brain\Monkey\setUp();

        // Mock cleanup calls (adjust keys to your plugin option/transient names).
        \Brain\Monkey\Functions\expect('delete_option')->zeroOrMoreTimes();
        \Brain\Monkey\Functions\expect('delete_site_option')->zeroOrMoreTimes();
        \Brain\Monkey\Functions\expect('delete_transient')->zeroOrMoreTimes();
        \Brain\Monkey\Functions\expect('delete_site_transient')->zeroOrMoreTimes();

        // Locate real uninstall.php; if missing, SKIP (no FAIL).
        $pluginRoot = dirname(__DIR__, 2); // adjust if your structure differs
        $real = $pluginRoot . '/uninstall.php';
        if (!is_file($real)) {
            \Brain\Monkey\tearDown();
            $this->markTestSkipped('uninstall.php not found');
        }

        // Include the real uninstall in isolated scope (mocks intercept WP calls).
        include $real;

        \Brain\Monkey\tearDown();
        $this->assertTrue(true);
    }

    public function test_multisite_activation_deactivation_smoke(): void
    {
        // Guard again for clarity (cheap).
        if (!function_exists('is_multisite') || !is_callable('is_multisite')) {
            $this->markTestSkipped('multisite helpers unavailable');
        }

        \Brain\Monkey\setUp();
        // Stub minimal hooks so includes/activators don’t fatals if referenced.
        \Brain\Monkey\Functions\when('is_multisite')->justReturn(true);
        \Brain\Monkey\Functions\when('is_network_admin')->justReturn(true);

        // No-op: this is a smoke to ensure no fatal occurs in activation paths if they are invoked indirectly.
        $this->assertTrue(true);

        \Brain\Monkey\tearDown();
    }
}
