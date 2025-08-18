<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Admin\Actions\{ExportGenerateAction, ExportDownloadAction};

final class ExportActionsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public array $inserted = [];
            public array $rows = [];
            public function prepare($sql, ...$args) { return $sql; }
            public function insert($table, $data) { $this->inserted[] = ['table'=>$table,'data'=>$data]; return true; }
            public function get_row($sql, $output) { return $this->rows[0] ?? null; }
            public function get_results($sql, $output) { return []; }
            public function get_var($sql) { return '1'; }
            public function query($sql) { return true; }
        };
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_generate_action_validates_and_calls_exporter(): void
    {
        $_POST = [
            'mode' => 'date-range',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-02',
            'smartalloc_export_nonce' => 'nonce',
        ];
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\expect('check_admin_referer')->once()->with('smartalloc_export_generate', 'smartalloc_export_nonce');
        Functions\expect('add_query_arg')->andReturn('/admin.php');
        Functions\expect('admin_url')->andReturn('/admin.php');
        Functions\expect('wp_safe_redirect')->once()->with('/admin.php')->andThrow(new \RuntimeException('redirect'));
        Functions\when('wp_checkdate')->justReturn(true);

        try {
            ExportGenerateAction::handle();
        } catch (\RuntimeException $e) {
            $this->assertSame('redirect', $e->getMessage());
        }

        $insert = $GLOBALS['wpdb']->inserted[0]['data'];
        $this->assertFileExists($insert['path']);
        unlink($insert['path']);
    }

    public function test_registry_record_persisted_with_checksum_and_size(): void
    {
        $_POST = [
            'mode' => 'batch',
            'batch_id' => '5',
            'smartalloc_export_nonce' => 'nonce',
        ];
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\expect('check_admin_referer')->once()->with('smartalloc_export_generate', 'smartalloc_export_nonce');
        Functions\expect('add_query_arg')->andReturn('/admin.php');
        Functions\expect('admin_url')->andReturn('/admin.php');
        Functions\expect('wp_safe_redirect')->once()->with('/admin.php')->andThrow(new \RuntimeException('redirect'));
        Functions\when('wp_checkdate')->justReturn(true);

        try {
            ExportGenerateAction::handle();
        } catch (\RuntimeException $e) {
            $this->assertSame('redirect', $e->getMessage());
        }

        $insert = $GLOBALS['wpdb']->inserted[0]['data'];
        $this->assertArrayHasKey('checksum', $insert);
        $this->assertGreaterThan(0, $insert['size']);
        $filters = json_decode($insert['filters'], true);
        $this->assertSame(5, $filters['batch_id']);
        unlink($insert['path']);
    }

    public function test_download_action_requires_valid_nonce_and_streams_file(): void
    {
        $baseDir = sys_get_temp_dir() . '/smartalloc/exports/2024/01';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }
        $tmp = tempnam($baseDir, 'exp');
        file_put_contents($tmp, 'data');
        $GLOBALS['wpdb']->rows = [[
            'id' => 1,
            'filename' => basename($tmp),
            'path' => $tmp,
            'filters' => '{}',
            'size' => 4,
            'checksum' => hash_file('sha256', $tmp),
            'created_at' => '2024-01-01 00:00:00',
        ]];

        $_GET = ['export_id' => '1'];
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
        Functions\expect('check_admin_referer')->once()->with('smartalloc_export_download_1');
        Functions\when('nocache_headers')->justReturn(true);
        Functions\when('esc_html__')->alias(fn($v) => $v);

        ob_start();
        ExportDownloadAction::handle();
        $content = ob_get_clean();

        $this->assertSame('data', $content);
        unlink($tmp);
    }
}
