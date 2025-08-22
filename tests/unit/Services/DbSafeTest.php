<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\DbSafe;

final class DbSafeTest extends BaseTestCase
{
    public function test_mismatch_throws(): void
    {
        global $wpdb;
        $wpdb = new class {
            public function prepare($sql, $params) { return vsprintf($sql, $params); }
        };
        $this->expectException(InvalidArgumentException::class);
        DbSafe::mustPrepare('SELECT * FROM t WHERE a=%d AND b=%d', [1]);
    }

    public function test_leftover_placeholder_throws(): void
    {
        global $wpdb;
        $wpdb = new class {
            public function prepare($sql, $params) { return $sql; }
        };
        $this->expectException(RuntimeException::class);
        DbSafe::mustPrepare('SELECT * FROM t WHERE a=%d', [1]);
    }

    public function test_returns_prepared_sql(): void
    {
        global $wpdb;
        $wpdb = new class {
            public function prepare($sql, $params) { return vsprintf($sql, $params); }
        };
        $sql = DbSafe::mustPrepare('SELECT * FROM t WHERE a=%d', [5]);
        $this->assertSame('SELECT * FROM t WHERE a=5', $sql);
    }
}
