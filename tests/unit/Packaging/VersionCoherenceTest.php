<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class VersionCoherenceTest extends BaseTestCase {
    public function test_version_coherence(): void {
        if (getenv('RUN_RELEASE_GATES') !== '1') {
            $this->markTestSkipped('release gates opt-in');
        }
        $root = dirname(__DIR__, 3);
        $cmd = escapeshellcmd("php {$root}/scripts/version-coherence.php");
        $output = shell_exec($cmd);
        $data = json_decode((string)$output, true);
        $mismatches = $data['summary']['mismatches'] ?? [];
        if (!empty($mismatches)) {
            $this->fail('version-coherence mismatches: ' . json_encode($mismatches));
        }
        $this->assertIsArray($data);
    }
}
