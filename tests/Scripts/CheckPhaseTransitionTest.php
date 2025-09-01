<?php
// phpcs:ignoreFile

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class CheckPhaseTransitionTest extends BaseTestCase
{
    public function test_phase_transition_fails_with_empty_features_and_succeeds_after_sync(): void
    {
        $tmp = sys_get_temp_dir() . '/sa_phase_' . uniqid();
        mkdir($tmp);
        exec('cp -R . ' . escapeshellarg($tmp) . ' >/dev/null 2>&1');

        $featuresPath = $tmp . '/features.json';
        $contextPath  = $tmp . '/ai_context.json';

        // Start with no features
        file_put_contents(
            $featuresPath,
            json_encode(['schema' => 1, 'features' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $context = json_decode(file_get_contents($contextPath), true);
        $context['features'] = [];
        file_put_contents($contextPath, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/check_phase_transition.sh >/dev/null 2>&1', $out, $code1);
        $this->assertSame(1, $code1);

        // Populate required features and re-run
        $features = [
            'schema'   => 1,
            'features' => [
                ['name' => 'rule_engine', 'status' => 'implemented'],
                ['name' => 'db_abstraction', 'status' => 'complete'],
            ],
        ];
        file_put_contents($featuresPath, json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Sync features into AI context before checking transition
        exec('cd ' . escapeshellarg($tmp) . ' && php scripts/sync-features-to-ai-context.php >/dev/null 2>&1');
        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/check_phase_transition.sh >/dev/null 2>&1', $out2, $code2);
        $this->assertSame(0, $code2);
        $synced = json_decode(file_get_contents($contextPath), true);
        $this->assertSame('implemented', $synced['features']['rule_engine']);
    }
}
