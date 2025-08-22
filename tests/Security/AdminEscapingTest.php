<?php
declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;


final class AdminEscapingTest extends \HttpTest
{
    private $origWpdb;

    protected function setUp(): void
    {
        Monkey\setUp();
        withCapability(true);

        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_html')->alias(fn($v) => htmlspecialchars((string) $v, ENT_QUOTES));
        Functions\when('esc_attr')->alias(fn($v) => htmlspecialchars((string) $v, ENT_QUOTES));
        Functions\when('__')->alias(fn($v) => $v);
        Functions\when('esc_url')->alias(fn($v) => $v);
        Functions\when('settings_fields')->alias(fn() => '');
        Functions\when('submit_button')->alias(fn() => '');
        Functions\when('selected')->alias(fn($a, $b, $echo = false) => $a === $b ? 'selected' : '');
        Functions\when('checked')->alias(fn($a, $b, $echo = false) => $a === $b ? 'checked' : '');
        Functions\when('admin_url')->alias(fn($p = '') => $p);
        Functions\when('wp_nonce_field')->alias(fn() => '');
        Functions\when('wp_nonce_url')->alias(fn($u) => $u);
        Functions\when('wp_enqueue_script')->alias(fn() => null);
        Functions\when('wp_enqueue_style')->alias(fn() => null);
        Functions\when('plugins_url')->alias(fn($p, $f = null) => $p);
        Functions\when('size_format')->alias(fn($v) => (string) $v);
        Functions\when('sanitize_textarea_field')->alias(fn($v) => $v);
        Functions\when('wp_die')->alias(fn() => '');

        global $wpdb;
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public function prepare(string $q, ...$a): string { return $q; }
            public function get_results($q, $o = 'OBJECT') { return []; }
            public function get_var($q) { return null; }
            public function insert($t, $d) { return true; }
            public function query($q) { return true; }
        };
    }

    protected function tearDown(): void
    {
        global $wpdb;
        unset($wpdb);
        Monkey\tearDown();
    }

    /**
     * @dataProvider pagesProvider
     */
    public function test_page_outputs_are_escaped(callable $renderer): void
    {
        $payload = '<img src=x onerror=alert(1)><script>alert(1)</script>';
        $html = $this->withBufferedOutput(fn() => renderPage($renderer, ['q' => $payload], ['q' => $payload]));

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function pagesProvider(): array
    {
        return [
            [[new \SmartAlloc\Admin\Pages\SettingsPage(), 'render']],
            [[new \SmartAlloc\Admin\Pages\ExportPage(), 'render']],
            [[new \SmartAlloc\Admin\Pages\ManualReviewPage(), 'render']],
            [[new \SmartAlloc\Admin\Pages\ReportsPage(), 'render']],
        ];
    }
}
