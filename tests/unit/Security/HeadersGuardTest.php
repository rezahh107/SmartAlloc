<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HeadersGuardTest extends TestCase
{
    public function test_no_wildcard_cors_headers(): void
    {
        if (getenv('RUN_SECURITY_TESTS') !== '1') {
            $this->markTestSkipped('security tests opt-in');
        }

        $root = dirname(__DIR__, 3);
        $violations = [];
        $allowTag = '@security-ok-header';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static function ($current, $key, $iterator) {
                    if ($iterator->hasChildren()) {
                        $basename = $current->getBasename();
                        $skip = ['vendor', 'node_modules', 'dist', '.git', 'tests'];
                        return !in_array($basename, $skip, true);
                    }
                    return $current->isFile() && $current->getExtension() === 'php';
                }
            )
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $lines = @file($path);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $num => $line) {
                if (strpos($line, 'Access-Control-Allow-Origin: *') !== false
                    && strpos($line, $allowTag) === false) {
                    $violations[] = $path . ':' . ($num + 1);
                }
            }
        }

        if (!empty($violations)) {
            $this->fail('Wildcard CORS headers found: ' . implode(', ', $violations));
        }

        $this->assertTrue(true);
    }
}
