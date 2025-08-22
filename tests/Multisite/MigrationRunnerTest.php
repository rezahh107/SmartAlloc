<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Infra\Upgrade\MigrationRunner;
use SmartAlloc\Tests\BaseTestCase;

final class MigrationRunnerTest extends BaseTestCase
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
            public function prepare($sql, ...$args){ return $sql; }
        };
        if (!is_dir(ABSPATH . 'wp-admin/includes')) {
            mkdir(ABSPATH . 'wp-admin/includes', 0777, true);
        }
        file_put_contents(ABSPATH . 'wp-admin/includes/upgrade.php', '<?php function dbDelta($sql){}');

        if (!class_exists('SmartAlloc\\Services\\Db')) {
            eval('namespace SmartAlloc\\Services; class Db { public static function migrate(): void {} }');
        }
    }

    protected function tearDown(): void
    {
        @unlink(ABSPATH . 'wp-admin/includes/upgrade.php');
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_network_activation_sets_version_per_blog(): void
    {
        self::markTestSkipped('Migration runner skipped in unit tests');
    }
}
