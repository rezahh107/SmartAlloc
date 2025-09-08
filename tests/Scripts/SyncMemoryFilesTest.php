<?php
// phpcs:ignoreFile

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class SyncMemoryFilesTest extends BaseTestCase
{
    public function test_new_feature_propagates_to_memory_files(): void
    {
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

    public function test_planned_feature_excluded_from_context(): void
    {
        $tmp = sys_get_temp_dir() . '/sa_sync_' . uniqid();
        mkdir($tmp);
        exec('cp -R . ' . escapeshellarg($tmp) . ' >/dev/null 2>&1');
        $ls = $tmp . '/ai_outputs/last_state.yml';
        $yaml = file_get_contents($ls);
        $yaml = preg_replace('/^feature:.*/m', 'feature: planned-feature', $yaml);
        $yaml = preg_replace('/^status:.*/m', 'status: planned', $yaml);
        file_put_contents($ls, $yaml);
        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/sync_memory_files.sh >/dev/null 2>&1');
        $this->assertStringContainsString('planned-feature', file_get_contents($tmp . '/FEATURES.md'));
        $names = array_column(json_decode(file_get_contents($tmp . '/ai_context.json'), true)['features'], 'name');
        $this->assertNotContains('planned-feature', $names);
    }
}
