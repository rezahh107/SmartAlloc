<?php
// phpcs:ignoreFile

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class AiContextFeaturesSyncTest extends BaseTestCase
{
    public function test_syncs_features_into_ai_context(): void
    {
        $tmp = sys_get_temp_dir() . '/sa_feat_sync_' . uniqid();
        mkdir($tmp);
        exec('cp -R . ' . escapeshellarg($tmp) . ' >/dev/null 2>&1');

        $featuresPath = $tmp . '/features.json';
        $contextPath  = $tmp . '/ai_context.json';

        $features = json_decode(file_get_contents($featuresPath), true);
        $features['features'][0]['status'] = 'red';
        file_put_contents($featuresPath, json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $context = json_decode(file_get_contents($contextPath), true);
        $context['features'] = [];
        file_put_contents($contextPath, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        exec('cd ' . escapeshellarg($tmp) . ' && php scripts/sync-features-to-ai-context.php >/dev/null 2>&1');

        $synced = json_decode(file_get_contents($contextPath), true);
        $this->assertSame('red', $synced['features']['DB Safety']);
    }

    public function test_handles_empty_features_array(): void
    {
        $tmp = sys_get_temp_dir() . '/sa_feat_sync_' . uniqid();
        mkdir($tmp);
        exec('cp -R . ' . escapeshellarg($tmp) . ' >/dev/null 2>&1');

        $featuresPath = $tmp . '/features.json';
        $contextPath  = $tmp . '/ai_context.json';

        file_put_contents(
            $featuresPath,
            json_encode(['features' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $context = json_decode(file_get_contents($contextPath), true);
        $context['features'] = ['Legacy' => 'green'];
        file_put_contents(
            $contextPath,
            json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        exec('cd ' . escapeshellarg($tmp) . ' && php scripts/sync-features-to-ai-context.php >/dev/null 2>&1');

        $synced = json_decode(file_get_contents($contextPath), true);
        $this->assertSame([], $synced['features']);
    }
}
