<?php
// phpcs:ignoreFile

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class UpdateStatePerformanceTest extends BaseTestCase {
    private string $script = __DIR__ . '/../../scripts/update_state.sh';

    /**
     * Run update_state.sh with a performance scenario.
     *
     * @param string $scenario PHP code to execute.
     * @param int $budgetMs Time budget in milliseconds.
     * @return array{scores:array,timing:array,features:string}
     */
    private function runScript(string $scenario, int $budgetMs): array {
        $dir = sys_get_temp_dir() . '/sa_perf_' . uniqid();
        mkdir($dir);
        $scenarioFile = $dir . '/scenario.php';
        file_put_contents($scenarioFile, $scenario);

        $ai = $dir . '/ai_context.json';
        $featuresFile = $dir . '/FEATURES.md';
        $cmd = sprintf(
            'SRC_DIR=%s TESTS_DIR=%s AI_CTX=%s FEATURES_MD=%s PERF_SCENARIO=%s SMARTALLOC_BUDGET_ALLOC_1K_MS=%d bash %s >/dev/null 2>&1',
            escapeshellarg($dir),
            escapeshellarg($dir),
            escapeshellarg($ai),
            escapeshellarg($featuresFile),
            escapeshellarg($scenarioFile),
            $budgetMs,
            escapeshellarg($this->script)
        );
        exec($cmd);
        $data = json_decode(file_get_contents($ai), true);
        $features = file_get_contents($featuresFile);
        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
        return [
            'scores' => $data['current_scores'],
            'timing' => $data['perf_timing'],
            'features' => $features,
        ];
    }

    public function test_perf_score_penalized_when_budget_exceeded(): void {
        $res = $this->runScript('<?php usleep(50000);', 10);
        $this->assertLessThan(10, $res['scores']['performance']);
        $this->assertTrue($res['timing']['penalized']);
        $this->assertStringContainsString('penalty', $res['features']);
    }
}
