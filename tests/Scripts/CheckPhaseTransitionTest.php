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
        $context['scores']   = [
            'security'    => 25,
            'logic'       => 25,
            'performance' => 25,
            'readability' => 25,
            'goal'        => 25,
        ];
        file_put_contents($contextPath, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/check_phase_transition.sh >/dev/null 2>&1', $out, $code1);
        $this->assertSame(1, $code1);

        // Populate required features and re-run
        $features = [
            'schema'   => 1,
            'features' => [
                ['name' => 'notification_system', 'status' => 'complete'],
                ['name' => 'circuit_breaker', 'status' => 'stable'],
            ],
        ];
        file_put_contents($featuresPath, json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Sync features into AI context before checking transition
        exec('cd ' . escapeshellarg($tmp) . ' && php scripts/sync-features-to-ai-context.php >/dev/null 2>&1');
        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/check_phase_transition.sh >/dev/null 2>&1', $out2, $code2);
        $this->assertSame(0, $code2);
        $synced = json_decode(file_get_contents($contextPath), true);
        $this->assertSame('complete', $synced['features']['notification_system']);
    }

    public function test_phase_transition_fails_when_features_file_missing_then_succeeds(): void
    {
        $tmp = sys_get_temp_dir() . '/sa_phase_' . uniqid();
        mkdir($tmp);
        exec('cp -R . ' . escapeshellarg($tmp) . ' >/dev/null 2>&1');

        $featuresPath = $tmp . '/features.json';
        $contextPath  = $tmp . '/ai_context.json';

        // Remove features.json to simulate missing file
        unlink($featuresPath);
        $context = json_decode(file_get_contents($contextPath), true);
        $context['features'] = [];
        $context['scores']   = [
            'security'    => 25,
            'logic'       => 25,
            'performance' => 25,
            'readability' => 25,
            'goal'        => 25,
        ];
        file_put_contents($contextPath, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/check_phase_transition.sh >/dev/null 2>&1', $out, $code1);
        $this->assertSame(1, $code1);

        // Create required features and re-run
        $features = [
            'schema'   => 1,
            'features' => [
                ['name' => 'notification_system', 'status' => 'complete'],
                ['name' => 'circuit_breaker', 'status' => 'stable'],
            ],
        ];
        file_put_contents($featuresPath, json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        exec('cd ' . escapeshellarg($tmp) . ' && php scripts/sync-features-to-ai-context.php >/dev/null 2>&1');
        exec('cd ' . escapeshellarg($tmp) . ' && bash scripts/check_phase_transition.sh >/dev/null 2>&1', $out2, $code2);
        $this->assertSame(0, $code2);
    }
}
