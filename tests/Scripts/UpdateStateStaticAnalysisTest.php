<?php
// phpcs:ignoreFile

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class UpdateStateStaticAnalysisTest extends BaseTestCase {
private string $script = __DIR__ . '/../../scripts/update_state.sh';

/**
 * Run update_state.sh against given PHP code.
 *
 * @param string $code PHP code snippet.
 * @return array Parsed ai_context.json data.
 */
private function runScript(string $code): array {
$dir = sys_get_temp_dir() . '/sa_state_' . uniqid();
mkdir($dir);
file_put_contents($dir . '/sample.php', $code);

$ai = $dir . '/ai_context.json';
$cmd = sprintf(
'SRC_DIR=%s TESTS_DIR=%s AI_CTX=%s FEATURES_MD=%s bash %s >/dev/null 2>&1',
escapeshellarg($dir),
escapeshellarg($dir),
escapeshellarg($ai),
escapeshellarg($dir . '/FEATURES.md'),
escapeshellarg($this->script)
);
exec($cmd);
    $data = json_decode(file_get_contents($ai), true);
    array_map('unlink', glob($dir . '/*'));
    rmdir($dir);
    return $data;
}

public function test_scores_with_clean_code(): void {
    $data = $this->runScript('<?php function ok(): int { return 1; }');
    $scores = $data['current_scores'];
    $analysis = $data['analysis'];
    $this->assertSame(25, $scores['security']);
    $this->assertSame(25, $scores['logic']);
    $this->assertSame(0, $analysis['security_errors']);
    $this->assertSame(0, $analysis['logic_errors']);
}

public function test_scores_reflect_error_counts(): void {
    $oneData = $this->runScript('<?php foo();');
    $twoData = $this->runScript('<?php foo(); bar();');
    $oneScores = $oneData['current_scores'];
    $twoScores = $twoData['current_scores'];
    $this->assertSame(20, $oneScores['security']);
    $this->assertSame(20, $oneScores['logic']);
    $this->assertSame(15, $twoScores['security']);
    $this->assertSame(15, $twoScores['logic']);
    $this->assertSame(1, $oneData['analysis']['security_errors']);
    $this->assertSame(1, $oneData['analysis']['logic_errors']);
    $this->assertSame(2, $twoData['analysis']['security_errors']);
    $this->assertSame(2, $twoData['analysis']['logic_errors']);
    $this->assertGreaterThan($twoScores['security'], $oneScores['security']);
    $this->assertGreaterThan($twoScores['logic'], $oneScores['logic']);
    $this->assertGreaterThan($twoScores['total'], $oneScores['total']);
}
}
