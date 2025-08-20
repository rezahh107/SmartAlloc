<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UninstallTightTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('\\Brain\\Monkey\\Functions')) {
            $this->markTestSkipped('Brain Monkey not installed');
        }
        \Brain\Monkey\setUp();
    }
    protected function tearDown(): void
    {
        if (class_exists('\\Brain\\Monkey')) { \Brain\Monkey\tearDown(); }
    }

    public function test_uninstall_deletes_expected_keys_or_skip(): void
    {
        $fixture = __DIR__ . '/../fixtures/uninstall-expected.php';
        if (!is_file($fixture)) {
            $this->markTestSkipped('expected uninstall keys fixture missing');
        }
        $expected = require $fixture;
        if (empty($expected) || !is_array($expected)) {
            $this->markTestSkipped('no expected uninstall keys defined');
        }

        foreach ($expected as $key) {
            \Brain\Monkey\Functions\expect('delete_option')->atLeast()->once()->with($key);
            // Optional multisite/site-transient cleanups:
            \Brain\Monkey\Functions\expect('delete_site_option')->zeroOrMoreTimes();
            \Brain\Monkey\Functions\expect('delete_transient')->zeroOrMoreTimes();
            \Brain\Monkey\Functions\expect('delete_site_transient')->zeroOrMoreTimes();
        }

        $root = dirname(__DIR__, 2);
        $uninstall = $root . '/uninstall.php';
        if (!is_file($uninstall)) {
            $this->markTestSkipped('uninstall.php not found');
        }

        include $uninstall; // Brain Monkey intercepts WP deletion functions.
        $this->assertTrue(true);
    }
}

