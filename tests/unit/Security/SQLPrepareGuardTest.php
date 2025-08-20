<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/scripts/scan-sql-prepare.php';

final class SQLPrepareGuardTest extends TestCase
{
    public function test_sql_queries_are_prepared(): void
    {
        if (getenv('RUN_SECURITY_TESTS') !== '1') {
            $this->markTestSkipped('security tests opt-in');
        }

        $root = dirname(__DIR__, 3);
        $violations = scan_sql_prepare($root);
        if (!empty($violations)) {
            $files = array_map(static fn(array $v): string => $v['file'] . ':' . $v['line'], $violations);
            $this->fail('Unprepared SQL queries: ' . implode(', ', $files));
        }

        $this->assertTrue(true);
    }
}
