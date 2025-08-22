<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class QAOrchestratorTest extends TestCase
{
    public function test_index_generated_with_rtl(): void
    {
        $root = sys_get_temp_dir() . '/qa-orch-' . uniqid();
        mkdir($root . '/scripts', 0777, true);
        mkdir($root . '/artifacts/coverage', 0777, true);
        mkdir($root . '/artifacts/schema', 0777, true);
        copy(dirname(__DIR__, 2) . '/scripts/qa-orchestrator.sh', $root . '/scripts/qa-orchestrator.sh');
        chmod($root . '/scripts/qa-orchestrator.sh', 0755);
        file_put_contents($root . '/artifacts/coverage/coverage.json', '{}');
        file_put_contents($root . '/artifacts/schema/schema-validate.json', '{}');
        $cmd = 'bash ' . escapeshellarg($root . '/scripts/qa-orchestrator.sh') . ' >/dev/null 2>&1';
        $code = 0;
        system($cmd, $code);
        $this->assertSame(0, $code);
        $index = $root . '/artifacts/qa/index.html';
        $this->assertFileExists($index);
        $html = (string)file_get_contents($index);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('coverage/coverage.json', $html);
        $this->assertStringContainsString('schema/schema-validate.json', $html);
    }
}
