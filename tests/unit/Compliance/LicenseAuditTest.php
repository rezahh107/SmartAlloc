<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;

require_once dirname(__DIR__, 3) . '/scripts/license-audit.php';

final class LicenseAuditTest extends TestCase
{
    private function mirror(string $src, string $dst): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $target = $dst . '/' . $it->getSubPathName();
            if ($item->isDir()) {
                @mkdir($target, 0777, true);
            } else {
                @mkdir(dirname($target), 0777, true);
                file_put_contents($target, file_get_contents($item->getPathname()));
            }
        }
    }

    public function test_reports_unapproved_licenses(): void
    {
        $fixture = dirname(__DIR__, 2) . '/fixtures/license';
        $root = vfsStream::setup('root');
        $this->mirror($fixture, vfsStream::url('root'));

        $tmp = sa_tests_temp_dir('license');
        $this->mirror(vfsStream::url('root'), $tmp);

        $cmd = 'php ' . escapeshellarg(dirname(__DIR__, 3) . '/scripts/license-audit.php') . ' ' . escapeshellarg($tmp);
        exec($cmd, $out, $code);
        $this->assertSame(0, $code);

        $json = file_get_contents($tmp . '/artifacts/compliance/license-audit.json');
        $report = json_decode($json, true);

        $this->assertSame(['total' => 2, 'unapproved' => 1], $report['counts']);
        $this->assertCount(2, $report['packages']);

        $this->assertSame('a/ok', $report['packages'][0]['name']);
        $this->assertTrue($report['packages'][0]['approved']);

        $this->assertSame('b/bad', $report['packages'][1]['name']);
        $this->assertFalse($report['packages'][1]['approved']);
    }
}

