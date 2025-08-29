<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class SyncMemoryFilesTest extends BaseTestCase
{
    private function ensurePyYamlOrSkip(): void
    {
        exec('python3 -c "import yaml" 2>/dev/null', $_, $code);
        if ($code !== 0) {
            $this->markTestSkipped('PyYAML not available');
        }
    }

    public function test_exports_last_state_to_json(): void
    {
        $this->ensurePyYamlOrSkip();

        $tmp = sys_get_temp_dir() . '/sa_sync_' . uniqid();
        @mkdir($tmp . '/ai_outputs', 0777, true);
        file_put_contents($tmp . '/ai_outputs/last_state.yml', "feature: test\nstatus: ok\nnotes: works\n");

        $cwd = getcwd();
        chdir($tmp);
        exec('git init >/dev/null 2>&1');
        exec('git config user.email tester@example.com');
        exec('git config user.name tester');
        file_put_contents('README.md', "test\n");
        exec('git add README.md ai_outputs/last_state.yml');
        exec('git commit -m init >/dev/null 2>&1');

        $script = escapeshellarg(realpath(__DIR__ . '/../../scripts/sync_memory_files.sh'));
        exec("bash $script >/dev/null 2>&1", $out, $exit);
        chdir($cwd);

        $this->assertSame(0, $exit);
        $jsonPath = $tmp . '/ai_outputs/last_state.json';
        $this->assertFileExists($jsonPath);
        $data = json_decode(file_get_contents($jsonPath), true);
        $this->assertSame(['feature' => 'test', 'status' => 'ok', 'notes' => 'works'], $data);
    }

    public function test_new_feature_propagates_to_memory_files(): void
    {
        $this->ensurePyYamlOrSkip();

        $tmp = sys_get_temp_dir() . '/sa_sync_' . uniqid();
        mkdir($tmp);
        exec('cp -R . ' . escapeshellarg($tmp) . ' >/dev/null 2>&1');
        $ls = $tmp . '/ai_outputs/last_state.yml';
        $yaml = file_get_contents($ls);
        $yaml = preg_replace('/^feature:.*/m', 'feature: unit-test-feature', $yaml);
        file_put_contents($ls, $yaml);
        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/sync_memory_files.sh >/dev/null 2>&1');

        $featuresMd = file_get_contents($tmp . '/FEATURES.md');
        $this->assertStringContainsString('unit-test-feature', $featuresMd);
        $ctx = json_decode(file_get_contents($tmp . '/ai_context.json'), true);
        $names = array_column($ctx['features'], 'name');
        $this->assertContains('unit-test-feature', $names);
    }
}
