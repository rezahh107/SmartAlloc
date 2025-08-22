<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class SQLInjectionTest extends BaseTestCase
{
    public function test_wpdb_usage_is_prepared_or_allowlisted(): void
    {
        if (getenv('RUN_SECURITY_TESTS') !== '1') {
            $this->markTestSkipped('security tests opt-in');
        }

        $files = $this->collectPhpFiles(dirname(__DIR__, 3));
        $violations = [];
        $found = false;

        foreach ($files as $file) {
            $src = file_get_contents($file) ?: '';
            if (strpos($src, '@security-ok-sql') !== false) {
                continue;
            }
            if (preg_match('/\$wpdb->(query|get_results|get_row|get_col|prepare)\s*\(/', $src)) {
                $found = true;
            }
            $hasQuery = preg_match('/\$wpdb->(query|get_results|get_row|get_col)\s*\(/', $src);
            $hasPrepareNearby = preg_match('/\$wpdb->prepare\s*\(/', $src);
            if ($hasQuery && !$hasPrepareNearby) {
                $violations[] = $file;
            }
        }

        if (!$found) {
            $this->markTestSkipped('no SQL usage found');
        }

        if (!empty($violations)) {
            $this->fail('Unprepared $wpdb usage in: ' . implode(', ', $violations));
        }

        $this->assertTrue(true);
    }

    private function collectPhpFiles(string $root): array
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $out = [];
        foreach ($rii as $f) {
            if ($f->isFile() && substr($f->getFilename(), -4) === '.php' && strpos($f->getPathname(), '/tests/') === false && strpos($f->getPathname(), '/vendor/') === false) {
                $out[] = $f->getPathname();
            }
        }
        return $out;
    }
}
