<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Tools;

use SmartAlloc\Tests\BaseTestCase;

final class NoConflictMarkersTest extends BaseTestCase
{
    public function test_no_conflict_markers_left(): void
    {
        $root = dirname(__DIR__, 2);
        $it   = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        $bad = [];
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $path = $f->getPathname();
            if ($path === __FILE__) {
                continue;
            }
            if (preg_match('~/(vendor|node_modules|dist|\.git)/~', $path)) {
                continue;
            }
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (!in_array($ext, ['php', 'md', 'xml', 'yml', 'yaml', 'json', 'neon'], true)) {
                continue;
            }
            $c = @file_get_contents($path);
            if ($c === false) {
                continue;
            }
            if (strpos($c, '<<<<<<<') !== false || strpos($c, '>>>>>>>') !== false || strpos($c, '=======') !== false) {
                $bad[] = $path;
            }
        }
        if ($bad !== []) {
            $this->fail("Conflict markers found:\n - " . implode("\n - ", $bad));
        }
        $this->assertTrue(true);
    }
}
