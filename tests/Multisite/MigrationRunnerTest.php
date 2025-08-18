<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Infra\Upgrade\MigrationRunner;

final class MigrationRunnerTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function get_charset_collate(){ return ''; }
            public function query($sql){ return 1; }
            public function insert($t,$d){ return 1; }
            public function get_var($sql){ return null; }
            public function get_results($sql){ return []; }
        };
        if (!is_dir(ABSPATH . 'wp-admin/includes')) {
            mkdir(ABSPATH . 'wp-admin/includes', 0777, true);
        }
        file_put_contents(ABSPATH . 'wp-admin/includes/upgrade.php', '<?php function dbDelta($sql){}');
    }

    protected function tearDown(): void
    {
        @unlink(ABSPATH . 'wp-admin/includes/upgrade.php');
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_network_activation_sets_version_per_blog(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $blogs = [1 => [], 2 => []];
        foreach ($blogs as $id => &$opts) {
            $GLOBALS['sa_options'] = $opts;
            MigrationRunner::maybeRun();
            $opts = $GLOBALS['sa_options'];
        }
        $this->assertSame(SMARTALLOC_DB_VERSION, $blogs[1]['smartalloc_db_version']);
        $this->assertSame(SMARTALLOC_DB_VERSION, $blogs[2]['smartalloc_db_version']);
    }
}
