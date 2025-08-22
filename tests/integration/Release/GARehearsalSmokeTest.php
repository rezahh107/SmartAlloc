<?php
namespace Release;

use SmartAlloc\Tests\BaseTestCase;

class GARehearsalSmokeTest extends BaseTestCase
{
    public function testRun(): void
    {
        if (getenv('RUN_GA_REHEARSAL') !== '1') {
            $this->markTestSkipped('RUN_GA_REHEARSAL not set');
        }
        $root = dirname(__DIR__, 3);
        $cmd = 'bash ' . escapeshellarg($root . '/scripts/ga-rehearsal.sh') . ' >/dev/null 2>&1';
        $result = 0;
        system($cmd, $result);
        $this->assertSame(0, $result, 'ga-rehearsal script failed');
        $this->assertFileExists($root . '/artifacts/ga/GA_REHEARSAL.txt');
        if (file_exists($root . '/artifacts/dist/manifest.json')) {
            $this->assertGreaterThan(0, filesize($root . '/artifacts/dist/manifest.json'));
        }
        if (file_exists($root . '/artifacts/dist/sbom.json')) {
            $this->assertGreaterThan(0, filesize($root . '/artifacts/dist/sbom.json'));
        }
        if (file_exists($root . '/artifacts/wporg/trunk/readme.txt')) {
            $this->assertGreaterThan(0, filesize($root . '/artifacts/wporg/trunk/readme.txt'));
        }
    }
}
