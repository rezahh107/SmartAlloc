<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Tests\Scoring;

use SmartAlloc\Tests\BaseTestCase;

final class UpdateStateTest extends BaseTestCase
{
    public function testPhaseWrittenAfterUpdate(): void
    {
        $tmp = sys_get_temp_dir() . '/sa_state_' . uniqid('', true);
        mkdir($tmp);
        exec('cp -R . ' . escapeshellarg($tmp) . ' >/dev/null 2>&1');

        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/update_state.sh >/dev/null 2>&1', $out1, $exit1);
        $this->assertSame(0, $exit1);

        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/check_phase_transition.sh >/dev/null 2>&1', $out2, $exit2);
        $this->assertSame(0, $exit2);

        $ctx = json_decode((string) file_get_contents($tmp . '/ai_context.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('foundation', $ctx['project_phase']);
        $features = (string) file_get_contents($tmp . '/FEATURES.md');
        $this->assertStringContainsString('Current Phase: foundation', $features);

        exec('rm -rf ' . escapeshellarg($tmp));
    }
}
