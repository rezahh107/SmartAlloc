<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DistAuditTest extends TestCase {
    public function test_dist_package_audit(): void {
        if (getenv('RUN_DIST_AUDIT') !== '1') {
            $this->markTestSkipped('dist audit opt-in');
        }
        $root = dirname(__DIR__, 3);
        $distPath = $root . '/dist';
        if (!is_dir($distPath) && !is_file($distPath)) {
            $this->markTestSkipped('dist package not found');
        }
        $cmd = escapeshellcmd("php {$root}/scripts/dist-audit.php " . escapeshellarg($distPath));
        $output = shell_exec($cmd);
        $data = json_decode((string)$output, true);
        $violations = $data['violations'] ?? [];
        if (!empty($violations)) {
            $this->fail('dist-audit violations: ' . json_encode($violations));
        }
        $this->assertIsArray($data);
    }
}
