<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use org\bovigo\vfs\vfsStream;

require_once dirname(__DIR__, 3) . '/scripts/scan-sql-prepare.php';

final class SqlPrepareScannerTest extends BaseTestCase
{
    /** Mirror vfs directory to real filesystem for CLI execution. */
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

    public function test_scanner_reports_and_allowlists(): void
    {
        $root = vfsStream::setup('root', null, [
            'safe-inline.php' => '<?php global $wpdb; $wpdb->get_results($wpdb->prepare("SELECT * FROM t WHERE id=%d",1));',
            'safe-var.php' => '<?php global $wpdb; $sql=$wpdb->prepare("SELECT * FROM t WHERE id=%d",1); $wpdb->query($sql);',
            'unsafe.php' => '<?php global $wpdb; $wpdb->query("SELECT * FROM t WHERE id=$id");',
            'allowlisted.php' => '<?php global $wpdb; $wpdb->get_var("SELECT NOW()");',
            'tools' => []
        ]);
        $finger = sha1('"SELECT NOW()"');
        file_put_contents(vfsStream::url('root/tools/sql-allowlist.json'), json_encode([
            'allowlisted.php' => [
                ['fingerprint' => $finger, 'reason' => 'test']
            ]
        ]));

        $tmp = sys_get_temp_dir() . '/sqlscan' . uniqid();
        $this->mirror(vfsStream::url('root'), $tmp);

        $cmd = 'php ' . escapeshellarg(dirname(__DIR__, 3) . '/scripts/scan-sql-prepare.php') . ' ' . escapeshellarg($tmp);
        exec($cmd, $output, $code);
        $this->assertSame(0, $code);

        $json = file_get_contents($tmp . '/artifacts/security/sql-prepare.json');
        $report = json_decode($json, true);

        $this->assertSame(4, $report['total_files_scanned']);
        $this->assertSame(['allowlisted' => 1, 'violations' => 1], $report['counts']);
        $this->assertCount(2, $report['violations']);
        $this->assertSame('allowlisted.php', $report['violations'][0]['file']);
        $this->assertTrue($report['violations'][0]['allowlisted']);
        $this->assertSame('unsafe.php', $report['violations'][1]['file']);
        $this->assertFalse($report['violations'][1]['allowlisted']);
    }
}
