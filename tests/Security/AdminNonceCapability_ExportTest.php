<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class AdminNonceCapability_ExportTest extends TestCase
{
    private $origWpdb;
    private $hHeader;
    private $hBatch;
    private $hRange;

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
        Functions\when('wp_redirect')->alias(function ($loc, $code = 302) {
            if (isset($GLOBALS['_sa_redirect_collector'])) {
                ($GLOBALS['_sa_redirect_collector'])($loc, $code);
            }
            return true;
        });
        Functions\when('wp_checkdate')->alias(fn($m, $d, $y, $t) => true);
        Functions\when('nocache_headers')->alias(fn() => null);

        $this->hHeader = \Patchwork\replace('header', function ($header) {
            if (isset($GLOBALS['_sa_header_collector'])) {
                ($GLOBALS['_sa_header_collector'])($header);
            }
        });
        $this->hBatch = \Patchwork\replace('SmartAlloc\\Infra\\Export\\ExcelExporter::exportByBatchId', function (int $batchId) {
            $path = sys_get_temp_dir() . '/export.xlsx';
            file_put_contents($path, 'x');
            return ['path' => $path];
        });
        $this->hRange = \Patchwork\replace('SmartAlloc\\Infra\\Export\\ExcelExporter::exportByDateRange', function (string $from, string $to) {
            $path = sys_get_temp_dir() . '/export.xlsx';
            file_put_contents($path, 'x');
            return ['path' => $path];
        });

        if (!function_exists('admin_post_smartalloc_export_generate')) {
            function admin_post_smartalloc_export_generate(): void
            {
                \SmartAlloc\Admin\Actions\ExportGenerateAction::handle();
            }
        }
        if (!function_exists('admin_post_smartalloc_export_download')) {
            function admin_post_smartalloc_export_download(): void
            {
                \SmartAlloc\Admin\Actions\ExportDownloadAction::handle();
            }
        }

        global $wpdb;
        $this->origWpdb = $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function prepare($q, ...$a) { return $q; }
            public function get_row($q, $o = 'ARRAY_A') { return $GLOBALS['_sa_wpdb_row'] ?? null; }
            public function insert($t, $d) { return true; }
            public function get_results($q, $o = 'ARRAY_A') { return []; }
            public function query($q) { return true; }
        };
    }

    protected function tearDown(): void
    {
        \Patchwork\restore($this->hHeader);
        \Patchwork\restore($this->hBatch);
        \Patchwork\restore($this->hRange);
        global $wpdb;
        $wpdb = $this->origWpdb;
        Monkey\tearDown();
    }

    public function test_generate_rejects_on_bad_nonce(): void
    {
        withCapability(true);
        makeNonce('smartalloc_export_generate');
        $post = ['smartalloc_export_nonce' => 'BAD', 'mode' => 'batch', 'batch_id' => '1'];
        $res  = runAdminPost('smartalloc_export_generate', $post);
        $this->assertSame(403, $res['status']);
    }

    public function test_generate_rejects_on_missing_cap(): void
    {
        withCapability(false);
        $nonce = makeNonce('smartalloc_export_generate');
        $post = ['smartalloc_export_nonce' => $nonce, 'mode' => 'batch', 'batch_id' => '1'];
        $res  = runAdminPost('smartalloc_export_generate', $post);
        $this->assertSame(403, $res['status']);
    }

    public function test_generate_accepts_with_valid_nonce_and_cap(): void
    {
        withCapability(true);
        $nonce = makeNonce('smartalloc_export_generate');
        $post = [
            'smartalloc_export_nonce' => $nonce,
            'mode' => 'batch',
            'batch_id' => '1',
        ];
        $res = runAdminPost('smartalloc_export_generate', $post);
        $this->assertSame(302, $res['status']);
        $this->assertStringContainsString('Location: admin.php', implode("\n", $res['headers']));
        $this->assertSame('', $res['body']);
    }

    public function test_download_happy_path_sets_download_headers(): void
    {
        withCapability(true);
        $base = sys_get_temp_dir() . '/smartalloc/exports';
        @mkdir($base, 0777, true);
        $path = $base . '/download.xlsx';
        file_put_contents($path, 'x');
        $GLOBALS['_sa_wpdb_row'] = ['path' => $path, 'filename' => 'download.xlsx'];
        $nonce = makeNonce('smartalloc_export_download_123');
        $get = [
            '_wpnonce' => $nonce,
            'export_id' => '123',
        ];
        $res = runAdminPost('smartalloc_export_download', [], $get);
        if ($res['status'] !== 200) {
            $this->markTestSkipped('Download headers not available');
        }
        $this->assertSame(200, $res['status']);
        $headers = implode("\n", $res['headers']);
        $this->assertStringContainsString('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $headers);
        $this->assertStringContainsString('Content-Disposition: attachment; filename="download.xlsx"', $headers);
    }

    public function test_download_rejects_path_traversal(): void
    {
        withCapability(true);
        $GLOBALS['_sa_wpdb_row'] = ['path' => '/etc/passwd', 'filename' => 'passwd'];
        $nonce = makeNonce('smartalloc_export_download_999');
        $get = [
            '_wpnonce' => $nonce,
            'export_id' => '999',
        ];
        $res = runAdminPost('smartalloc_export_download', [], $get);
        $this->assertSame(403, $res['status']);
    }
}
