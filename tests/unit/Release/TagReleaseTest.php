<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class TagReleaseTest extends BaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
        @mkdir($this->root . '/artifacts', 0777, true);
        @mkdir($this->root . '/artifacts/dist', 0777, true);
        @unlink($this->root . '/artifacts/dist/audit.json');
    }

    public function test_rc_tag_produces_artifacts_and_notes(): void
    {
        $tag = 'v9.9.9-rc.0';
        $cmd = sprintf('%s %s --tag=%s --enforce=false',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->root . '/scripts/tag-release.php'),
            escapeshellarg($tag)
        );
        exec($cmd, $out, $rc);
        $this->assertSame(0, $rc, 'rc tag-release exited non-zero');
        $output = implode("\n", $out);
        $this->assertStringContainsString('GA Enforcer: advisory', $output);
        $relDir = $this->root . '/artifacts/release/' . $tag;
        $this->assertFileExists($relDir . '/release-notes.md');
        $this->assertFileExists($this->root . '/artifacts/release/tag.json');
        exec(sprintf('git tag -d %s', escapeshellarg($tag)));
    }

    public function test_ga_tag_enforces_warnings(): void
    {
        $tagFail = 'v9.9.9';
        $gaReady = $this->root . '/artifacts/ga';
        @mkdir($gaReady, 0777, true);
        file_put_contents($gaReady . '/GA_READY.txt', "WARN test\n");
        $cmd = sprintf('%s %s --tag=%s --enforce=true',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->root . '/scripts/tag-release.php'),
            escapeshellarg($tagFail)
        );
        exec($cmd, $_, $rc);
        $this->assertNotSame(0, $rc, 'ga tag-release should fail on warnings');
        @unlink($gaReady . '/GA_READY.txt');
        exec(sprintf('git tag -d %s 2>/dev/null', escapeshellarg($tagFail)));
        $tagPass = 'v9.9.10';
        $cmd = sprintf('%s %s --tag=%s --enforce=true',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->root . '/scripts/tag-release.php'),
            escapeshellarg($tagPass)
        );
        exec($cmd, $_, $rc2);
        $this->assertSame(0, $rc2, 'ga tag-release should pass when clean');
        $relDir = $this->root . '/artifacts/release/' . $tagPass;
        $this->assertFileExists($relDir . '/SHA256SUMS.txt');
        exec(sprintf('git tag -d %s 2>/dev/null', escapeshellarg($tagPass)));
    }
}
