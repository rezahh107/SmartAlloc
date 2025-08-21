<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ChangelogGuardTest extends TestCase {
    public function test_changelog_guard(): void {
        if (getenv('RUN_RELEASE_GATES') !== '1') {
            $this->markTestSkipped('release gates opt-in');
        }
        $root = dirname(__DIR__, 3);
        $cmd = escapeshellcmd("php {$root}/scripts/changelog-guard.php");
        $output = shell_exec($cmd);
        $data = json_decode((string)$output, true);
        $ok = $data['summary']['ok'] ?? false;
        if (!$ok) {
            $this->fail('changelog guard mismatches: ' . json_encode($data['summary']['errors'] ?? []));
        }
        $this->assertIsArray($data);
    }
}
