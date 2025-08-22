<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class AdminNonceCapabilityTest extends BaseTestCase
{
    private $origWpdb;

    protected function setUp(): void
    {
        Monkey\setUp();
        
        Functions\when('esc_html__')->alias(fn($v) => $v);
        Functions\when('esc_html')->alias(fn($v) => $v);
        Functions\when('esc_attr')->alias(fn($v) => $v);
        Functions\when('__')->alias(fn($v) => $v);
        Functions\when('esc_url')->alias(fn($v) => $v);
        Functions\when('sanitize_textarea_field')->alias(fn($v) => $v);
        Functions\when('wp_die')->alias(function ($message = '', $title = '', $args = []) {
            $status = $args['response'] ?? 403;
            if (isset($GLOBALS['_sa_die_collector'])) {
                ($GLOBALS['_sa_die_collector'])($message, $title, ['response' => $status]);
            }
            return '';
        });
        Functions\when('add_query_arg')->alias(fn($a, $u) => $u);
        Functions\when('admin_url')->alias(fn($p = '') => $p);
        Functions\when('wp_safe_redirect')->alias(fn($loc, $code = 302) => wp_redirect($loc, $code));
        Functions\when('wp_checkdate')->alias(fn($m, $d, $y, $t) => true);
        Functions\when('nocache_headers')->alias(fn() => null);
        Functions\when('size_format')->alias(fn($v) => (string) $v);

        if (!function_exists('admin_post_smartalloc_reports_csv')) {
            function admin_post_smartalloc_reports_csv(): void {
                \SmartAlloc\Admin\Pages\ReportsPage::downloadCsv();
            }
        }

        global $wpdb;
        $this->origWpdb = $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function prepare($q, ...$a) { return $q; }
            public function query($q) { return true; }
            public function get_results($q, $o = 'OBJECT') { return $GLOBALS['_sa_wpdb_results'] ?? []; }
            public function get_row($q, $o = 'OBJECT') { return $GLOBALS['_sa_wpdb_row'] ?? null; }
            public function get_var($q) { return $GLOBALS['_sa_wpdb_var'] ?? null; }
            public function insert($t, $d) { return true; }
            public function update($t, $d, $w) { return true; }
            public function delete($t, $w) { return true; }
            public function replace($t, $d) { return true; }
            public function get_charset_collate() { return ''; }
        };
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->origWpdb;
        Monkey\tearDown();
        unset($GLOBALS['_sa_wpdb_row'], $GLOBALS['_sa_wpdb_results'], $GLOBALS['_sa_wpdb_var']);
    }

    /**
     * @dataProvider actionsProvider
     */
    public function test_rejects_when_nonce_invalid(string $action): void
    {
        withCapability(true);
        $post = $get = [];
        makeNonce('smartalloc_reports_csv');
        $get = ['smartalloc_reports_nonce' => 'BAD'];
        $res = runAdminPost($action, $post, $get);
        $this->assertSame(403, $res['status']);
    }

    /**
     * @dataProvider actionsProvider
     */
    public function test_rejects_when_capability_missing(string $action): void
    {
        withCapability(false);
        $post = $get = [];
        $nonce = makeNonce('smartalloc_reports_csv');
        $get = ['smartalloc_reports_nonce' => $nonce];
        $res = runAdminPost($action, $post, $get);
        $this->assertSame(403, $res['status']);
    }

    /**
     * @dataProvider actionsProvider
     */
    public function test_accepts_with_valid_nonce_and_capability(string $action): void
    {
        withCapability(true);
        $post = $get = [];
        $nonce = makeNonce('smartalloc_reports_csv');
        $get = ['smartalloc_reports_nonce' => $nonce];
        $GLOBALS['_sa_wpdb_results'] = [];
        $GLOBALS['_sa_wpdb_var'] = 0;
        $res = runAdminPost($action, $post, $get);
        $this->assertContains($res['status'], [200, 302]);
    }

    public function actionsProvider(): array
    {
        return [
            ['smartalloc_reports_csv'],
        ];
    }
}
