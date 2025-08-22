<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class ArchitectureTest extends BaseTestCase
{
    private function scanFiles(string $path, callable $filter): array
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $offenders = [];
        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname()) ?: '';
            if ($filter($content)) {
                $offenders[] = $file->getPathname();
            }
        }
        return $offenders;
    }

    public function test_domain_layer_independence(): void
    {
        $paths = [
            __DIR__.'/../../src/Command',
            __DIR__.'/../../src/Event',
            __DIR__.'/../../src/Contracts',
        ];
        $offenders = [];
        foreach ($paths as $p) {
            if (!is_dir($p)) { continue; }
            $offenders = array_merge($offenders, $this->scanFiles($p, function(string $c){
                return (bool) preg_match('/global \$wpdb|wp_\w+\(|get_option\(/i', $c);
            }));
        }
        // check Allocation services separately
        foreach (glob(__DIR__.'/../../src/Services/Allocation*.php') as $file) {
            $c = file_get_contents($file) ?: '';
            if (preg_match('/global \$wpdb|wp_\w+\(|get_option\(/i', $c)) {
                $offenders[] = $file;
            }
        }
        $this->assertSame([], $offenders, 'WP globals used: '.implode(', ', $offenders));
    }

    public function test_service_layer_database_independence(): void
    {
        $paths = [__DIR__.'/../../src/Domain'];
        foreach (glob(__DIR__.'/../../src/Services/Allocation*.php') as $f) { $paths[] = $f; }
        $offenders = [];
        foreach ($paths as $p) {
            if (is_dir($p)) {
                $offenders = array_merge($offenders, $this->scanFiles($p, function(string $c){
                    return (bool) preg_match('/mysqli_|new\s+PDO/i', $c);
                }));
            } else {
                $c = file_get_contents($p) ?: '';
                if (preg_match('/mysqli_|new\s+PDO/i', $c)) {
                    $offenders[] = $p;
                }
            }
        }
        $this->assertSame([], $offenders, 'Raw DB usage: '.implode(', ', $offenders));
    }

    public function test_naming_conventions(): void
    {
        $files = glob(__DIR__.'/../../src/Services/Allocation*.php');
        $bad = [];
        foreach ($files as $file) {
            $content = file_get_contents($file) ?: '';
            if (preg_match('/class\s+(\w+)/', $content, $m)) {
                $class = $m[1];
                if (!preg_match('/(Service|Controller|Listener|Command)$/', $class)) {
                    $bad[] = $file;
                }
            }
            if (preg_match_all('/function\s+(\w+)\s*\(/', $content, $m)) {
                foreach ($m[1] as $method) {
                    if ($method[0] !== '_' && !preg_match('/^[a-z][A-Za-z0-9]*$/', $method)) {
                        $bad[] = $file."::{$method}";
                    }
                }
            }
            if (preg_match_all('/const\s+(\w+)/', $content, $m)) {
                foreach ($m[1] as $const) {
                    if ($const !== strtoupper($const)) {
                        $bad[] = $file."::{$const}";
                    }
                }
            }
        }
        $this->assertSame([], $bad, 'Naming violations: '.implode(', ', $bad));
    }

    public function test_dependency_direction(): void
    {
        $scanDirs = [__DIR__.'/../../src/Contracts', __DIR__.'/../../src/Services'];
        $offenders = [];
        foreach ($scanDirs as $dir) {
            $offenders = array_merge($offenders, $this->scanFiles($dir, function(string $c){
                return (bool) preg_match('/SmartAlloc\\\\(Integration|Http|Admin)\\\\/i', $c) && preg_match('/namespace\s+SmartAlloc\\\\(?!Integration|Http|Admin)/', $c);
            }));
        }
        $this->assertSame([], $offenders, 'Dependency violations: '.implode(', ', $offenders));
    }
}
