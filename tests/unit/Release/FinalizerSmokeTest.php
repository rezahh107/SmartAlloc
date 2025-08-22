<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class FinalizerSmokeTest extends BaseTestCase {
    public function test_release_finalizer_artifacts_exist(): void {
        if (getenv('RUN_RELEASE_FINAL') !== '1') {
            $this->markTestSkipped('release finalizer opt-in');
        }
        $root = dirname(__DIR__, 3);
        $script = $root . '/scripts/release-finalizer.sh';
        $this->assertFileExists($script);
        $cmd = sprintf('bash %s', escapeshellarg($script));
        exec($cmd, $_, $code);
        $this->assertSame(0, $code, 'release-finalizer exited non-zero');
        $dist = $root . '/artifacts/dist';
        $this->assertFileExists($dist . '/manifest.json');
        $this->assertFileExists($dist . '/sbom.json');
        $this->assertFileExists($dist . '/release-notes.md');
    }
}
