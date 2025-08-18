<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use SmartAlloc\Cron\RetentionTasks;
use SmartAlloc\Tests\BaseTestCase;

if (!class_exists('WP_Error')) {
    class WP_Error {}
}

final class RetentionTasksTest extends BaseTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/sa_' . uniqid();
        mkdir($this->dir . '/smart-alloc/logs', 0777, true);
        $old = $this->dir . '/old.csv';
        file_put_contents($old, 'x');
        touch($old, time() - 2 * 86400);
        $new = $this->dir . '/new.csv';
        file_put_contents($new, 'y');
        $GLOBALS['sa_options']['smartalloc_settings'] = [
            'export_retention_days' => 1,
            'log_retention_days' => 1,
        ];
        $GLOBALS['wp_upload_dir_basedir'] = $this->dir;
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public array $rows;
            public array $deleted = [];
            public function get_results($sql, $output) { return $this->rows; }
            public function prepare($q, $id) { return str_replace('%d', (string)$id, $q); }
            public function query($sql) { $this->deleted[] = $sql; }
        };
        $wpdb->rows = [
            ['id' => 1, 'path' => $this->dir . '/old.csv', 'created_at' => date('Y-m-d H:i:s', time()-2*86400)],
            ['id' => 2, 'path' => $this->dir . '/new.csv', 'created_at' => date('Y-m-d H:i:s')],
        ];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    public function test_deletes_old_exports(): void
    {
        RetentionTasks::run();
        $this->assertFalse(file_exists($this->dir . '/old.csv'));
        $this->assertTrue(file_exists($this->dir . '/new.csv'));
        global $wpdb;
        $this->assertNotEmpty($wpdb->deleted);
    }
}
