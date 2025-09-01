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
 * @return array Parsed current_scores from ai_context.json.
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
return $data['current_scores'];
}

public function test_scores_with_clean_code(): void {
    $scores = $this->runScript('<?php function ok(): int { return 1; }');
    $this->assertSame(25, $scores['security']);
    $this->assertSame(25, $scores['logic']);
}

public function test_scores_reflect_error_counts(): void {
    $oneError = $this->runScript('<?php foo();');
    $twoErrors = $this->runScript('<?php foo(); bar();');
    $this->assertSame(20, $oneError['security']);
    $this->assertSame(20, $oneError['logic']);
    $this->assertSame(15, $twoErrors['security']);
    $this->assertSame(15, $twoErrors['logic']);
    $this->assertGreaterThan($twoErrors['security'], $oneError['security']);
    $this->assertGreaterThan($twoErrors['logic'], $oneError['logic']);
}
}
