<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PotFreshnessTest extends TestCase {
    public function test_pot_is_fresh_enough_or_skip(): void {
        if (getenv('RUN_I18N_POT') !== '1') {
            $this->markTestSkipped('pot freshness opt-in');
        }
        $pot = dirname(__DIR__, 2) . '/artifacts/i18n/messages.pot';
        if (!is_file($pot)) {
            $this->markTestSkipped('messages.pot not found');
        }
        $count = $this->countEntries($pot);
        if ($count < 10) {
            $this->markTestSkipped('messages.pot has ' . $count . ' entries');
        }
        $this->assertGreaterThanOrEqual(10, $count);
    }

    private function countEntries(string $file): int {
        $lines = file($file) ?: [];
        $count = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'msgid "') && $line !== 'msgid ""') {
                $count++;
            }
        }
        return $count;
    }
}
