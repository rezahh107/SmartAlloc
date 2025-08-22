<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class PotFreshnessTest extends BaseTestCase {
    public function test_pot_refresh_or_skip(): void {
        if (getenv('RUN_I18N_POT') !== '1') {
            $this->markTestSkipped('pot refresh opt-in');
        }
        $root = dirname(__DIR__, 2);
        $script = $root . '/scripts/pot-refresh.php';
        $json = $root . '/artifacts/i18n/pot-refresh.json';
        $pot = $root . '/artifacts/i18n/messages.pot';
        @unlink($json);
        @unlink($pot);
        exec(PHP_BINARY . ' ' . escapeshellarg($script), $output, $code);
        $this->assertSame(0, $code, 'pot-refresh exit code');
        $this->assertFileExists($pot, 'messages.pot missing');
        $data = json_decode((string) file_get_contents($json), true);
        $this->assertIsArray($data, 'pot-refresh.json invalid');
        $this->assertGreaterThanOrEqual(10, (int) ($data['pot_entries'] ?? 0), 'pot_entries < 10');
    }
}
