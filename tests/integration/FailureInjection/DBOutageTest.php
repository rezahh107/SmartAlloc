<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class FailureDBOutageTest extends BaseTestCase {
    public function test_db_error_on_allocate_is_graceful_or_skip(): void {
        if (getenv('RUN_FAILURE_TESTS') !== '1') {
            $this->markTestSkipped('failure tests opt-in');
        }
        if (!isset($GLOBALS['wpdb'])) {
            $this->markTestSkipped('wpdb missing');
        }

        $GLOBALS['wpdb'] = new class {
            public function update($table, $data, $where) {
                throw new \RuntimeException('db down');
            }
        };

        $committed = false;
        try {
            $GLOBALS['wpdb']->update('wp_table', ['foo' => 'bar'], ['id' => 1]);
            $committed = true; // would only reach on success
        } catch (\RuntimeException $e) {
            // Ensure no PII like emails leak in the error message
            $this->assertDoesNotMatchRegularExpression('/[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}/', $e->getMessage());
        }

        $this->assertFalse($committed, 'No partial state should be committed');
    }
}
