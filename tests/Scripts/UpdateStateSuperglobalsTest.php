<?php
// phpcs:ignoreFile

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class UpdateStateSuperglobalsTest extends BaseTestCase {
    private string $script = __DIR__ . '/../../scripts/update_state.sh';

    private function runScript(string $code): array {
        $dir = sys_get_temp_dir() . '/sa_super_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/sample.php', $code);

        $ai = $dir . '/ai_context.json';
        $env = [
            'SRC_DIR'     => $dir,
            'TESTS_DIR'   => $dir,
            'AI_CTX'      => $ai,
            'FEATURES_MD' => $dir . '/FEATURES.md',
        ];
        $envStr = '';
        foreach ($env as $k => $v) {
            $envStr .= sprintf('%s=%s ', $k, escapeshellarg((string) $v));
        }
        $cmd = sprintf('%sbash %s >/dev/null 2>&1', $envStr, escapeshellarg($this->script));
        exec($cmd);
        $data = json_decode(file_get_contents($ai), true);
        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
        return $data;
    }

    public function test_unsanitized_superglobal_reduces_score(): void {
        $clean = $this->runScript('<?php $a = sanitize_text_field($_GET["a"]);');
        $dirty = $this->runScript('<?php $a = $_GET["a"];');

        $this->assertSame(0, count($clean['current_scores']['red_flags']));
        $this->assertGreaterThan(0, count($dirty['current_scores']['red_flags']));
        $this->assertTrue($dirty['current_scores']['total'] < $clean['current_scores']['total']);
        $this->assertSame(15, $dirty['current_scores']['red_flags'][0]['severity']);
    }
}
