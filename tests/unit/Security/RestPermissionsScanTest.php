<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use org\bovigo\vfs\vfsStream;

require_once dirname(__DIR__, 3) . '/scripts/scan-rest-permissions.php';

final class RestPermissionsScanTest extends BaseTestCase
{
    private function mirror(string $src, string $dst): void
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
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

    public function test_scanner_extracts_and_warns(): void
    {
        $root = vfsStream::setup('root', null, [
            'allow.php' => "<?php\nregister_rest_route('foo/v1','/allow',[ 'methods'=>'GET','permission_callback'=>'__return_true','callback'=>'cb']);", 
            'bad.php' => "<?php\nregister_rest_route('foo/v1','/bad',[ 'methods'=>'GET','permission_callback'=>'__return_true','callback'=>'cb']);", 
            'mut.php' => "<?php\nregister_rest_route('foo/v1','/mut',[ 'methods'=>'POST','permission_callback'=>'__return_true','callback'=>'cb']);", 
            'qa' => ['allowlist' => ['rest-permissions.yml' => "- foo/v1/allow\n"]],
        ]);
        $tmp = sys_get_temp_dir() . '/restscan' . uniqid();
        $this->mirror(vfsStream::url('root'), $tmp);

        $cmd = 'php ' . escapeshellarg(dirname(__DIR__, 3) . '/scripts/scan-rest-permissions.php') . ' ' . escapeshellarg($tmp);
        exec($cmd, $out, $code);
        $this->assertSame(0, $code);

        $json = file_get_contents($tmp . '/artifacts/security/rest-permissions.json');
        $report = json_decode($json, true);
        $this->assertSame(3, $report['summary']['routes']);
        $this->assertSame(3, $report['summary']['warnings']);
        $this->assertSame(2, $report['summary']['mutating_warnings']);
        $this->assertSame(1, $report['summary']['readonly_warnings']);

        $warns = array_values(array_filter($report['warnings'], fn($w) => $w['route'] === 'foo/v1/bad'));
        $this->assertNotEmpty($warns);
        $warn = $warns[0];
        $this->assertSame('foo/v1/bad', $warn['route']);
        $expectedFinger = sha1($warn['file'] . ':2:foo/v1/bad');
        $this->assertSame($expectedFinger, $warn['fingerprint']);
    }
}
