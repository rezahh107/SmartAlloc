<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class SyncMemoryFilesTest extends BaseTestCase
{
    public function test_exports_last_state_to_json(): void
    {
        exec('python3 -c "import yaml" 2>/dev/null', $_, $code);
        if ($code !== 0) {
            $this->markTestSkipped('PyYAML not available');
        }

        $tmp = sys_get_temp_dir() . '/sa_sync_' . uniqid();
        @mkdir($tmp . '/ai_outputs', 0777, true);
        copy(__DIR__ . '/../../ai_outputs/last_state.yml', $tmp . '/ai_outputs/last_state.yml');

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

        $expected = [];
        foreach (file(__DIR__ . '/../../ai_outputs/last_state.yml') as $line) {
            if (preg_match('/^(feature|status|notes):\s*(.+)$/', trim($line), $m)) {
                $expected[$m[1]] = trim($m[2], "'\"");
            }
        }

        $this->assertSame($expected, $data);
    }
}
