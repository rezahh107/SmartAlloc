<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use org\bovigo\vfs\vfsStream;

require_once dirname(__DIR__, 3) . '/scripts/scan-secrets.php';

final class SecretsScanTest extends BaseTestCase
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

    public function test_scanner_reports_allowlist_and_entropy(): void
    {
        $secret = 'AKIA1234567890123456';
        $allow  = 'AKIA0000000000000000';
        $entropy = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';

        $root = vfsStream::setup('root', null, [
            'bad.txt'   => $secret,            // should report
            'allow.txt' => $allow,             // allowlisted
            'rand.txt'  => $entropy,           // entropy only
        ]);

        $hash = sha1($allow);
        file_put_contents(vfsStream::url('root/.qa-allowlist.json'), json_encode([
            'secrets' => [
                ['pattern' => $hash, 'reason' => 'fixture'],
            ],
        ]));

        $tmp = sa_tests_temp_dir('secrets');
        $this->mirror(vfsStream::url('root'), $tmp);

        $cmd = 'php ' . escapeshellarg(dirname(__DIR__, 3) . '/scripts/scan-secrets.php') . ' ' . escapeshellarg($tmp);
        exec($cmd, $out, $code);
        $this->assertSame(0, $code);

        $json = file_get_contents($tmp . '/artifacts/security/secrets.json');
        $report = json_decode($json, true);

        $this->assertSame(['violations' => 2, 'allowlisted' => 1], $report['counts']);
        $this->assertCount(3, $report['findings']);

        $byFile = [];
        foreach ($report['findings'] as $f) {
            $byFile[$f['file']] = $f;
        }

        $this->assertSame('aws_access_key', $byFile['bad.txt']['kind']);
        $this->assertFalse($byFile['bad.txt']['allowlisted']);

        $this->assertTrue($byFile['allow.txt']['allowlisted']);
        $this->assertSame('fixture', $byFile['allow.txt']['reason']);

        $this->assertSame('high_entropy', $byFile['rand.txt']['kind']);
    }
}

