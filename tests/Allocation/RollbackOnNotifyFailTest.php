<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for allocation rollback on notification failures.
 *
 * @group allocation
 */
final class RollbackOnNotifyFailTest extends TestCase {
    /**
     * Test that allocations are rolled back when notifications fail.
     */
    public function test_allocation_rolled_back_when_notification_final_fails(): void {
        $this->markTestIncomplete(
            'Bind to Allocation facade + RetryingMailer final-fail path; expect rollback and error log entry.'
        );
    }
}
