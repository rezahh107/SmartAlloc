<?php
// phpcs:ignoreFile

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class UpdateStateTest extends BaseTestCase
{
    public function test_phase_written_after_update(): void
    {
        $tmp = sys_get_temp_dir() . '/sa_state_' . uniqid();
        mkdir($tmp);
        exec('cp -R . ' . escapeshellarg($tmp) . ' >/dev/null 2>&1');
        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/update_state.sh >/dev/null 2>&1');
        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/check_phase_transition.sh >/dev/null 2>&1');
        $ctx = json_decode(file_get_contents($tmp . '/ai_context.json'), true);
        $this->assertSame('foundation', $ctx['project_phase']);
        $this->assertStringContainsString('Current Phase: foundation', file_get_contents($tmp . '/FEATURES.md'));
    }
}
