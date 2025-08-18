<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class AdminNonceCapability_ManualReviewTest extends TestCase
{
    protected function setUp(): void
    {
        Monkey\setUp();
        Functions\when('wp_die')->alias(function ($message = '', $title = '', $args = []) {
            $status = $args['response'] ?? 500;
            if (isset($GLOBALS['_sa_die_collector'])) {
                ($GLOBALS['_sa_die_collector'])($message, $title, ['response' => $status]);
            }
            return '';
        });
        Functions\when('wp_send_json_success')->alias(function ($data = null, $status = null) {
            $json = wp_json_encode(['success' => true, 'data' => $data]);
            wp_die($json, '', ['response' => $status ?? 200]);
        });
        Functions\when('wp_send_json_error')->alias(function ($data = null, $status = null) {
            $json = wp_json_encode(['success' => false, 'data' => $data]);
            wp_die($json, '', ['response' => $status ?? 200]);
        });
        Functions\when('get_current_user_id')->alias(fn() => 1);
        Functions\when('sanitize_textarea_field')->alias(fn($v) => $v);
        Functions\when('sanitize_key')->alias(fn($v) => $v);

        if (!function_exists('admin_post_smartalloc_manual_approve')) {
            function admin_post_smartalloc_manual_approve(): void { \SmartAlloc\Admin\Actions\ManualApproveAction::handle(); }
            function admin_post_smartalloc_manual_assign(): void { \SmartAlloc\Admin\Actions\ManualAssignAction::handle(); }
            function admin_post_smartalloc_manual_reject(): void { \SmartAlloc\Admin\Actions\ManualRejectAction::handle(); }
        }

        $GLOBALS['smartalloc_repo'] = new class {
            public function findByEntryId($id) { return ['candidates' => [['mentor_id' => 456]]]; }
            public function approveManual($id, $mentorId, $reviewerId, $notes) { return new class { public function to_array() { return ['ok' => true]; } }; }
            public function rejectManual($id, $reviewerId, $reason, $notes) { return true; }
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['smartalloc_repo']);
        Monkey\tearDown();
    }

    private function ok(string $action, array $post = []): array
    {
        withCapability(true);
        $post['nonce'] = makeNonce('smartalloc_manual_action');
        return runAdminPost($action, $post);
    }

    public function test_approve_requires_nonce_and_cap(): void
    {
        withCapability(true);
        makeNonce('smartalloc_manual_action');
        $resBadNonce = runAdminPost('smartalloc_manual_approve', ['entry_ids' => [123], 'nonce' => 'BAD']);
        $this->assertSame(403, $resBadNonce['status']);
        withCapability(false);
        $resNoCap = runAdminPost('smartalloc_manual_approve', ['entry_ids' => [123], 'nonce' => makeNonce('smartalloc_manual_action')]);
        $this->assertSame(403, $resNoCap['status']);
    }

    public function test_approve_happy_path(): void
    {
        $res = $this->ok('smartalloc_manual_approve', ['entry_ids' => [123], 'notes' => 'ok']);
        $this->assertContains($res['status'], [200, 302]);
        $this->assertStringContainsString('success', $res['body']);
    }

    public function test_assign_happy_path(): void
    {
        $res = $this->ok('smartalloc_manual_assign', ['entry_ids' => [123], 'mentor_id' => 456]);
        $this->assertContains($res['status'], [200, 302]);
        $this->assertStringContainsString('success', $res['body']);
    }

    public function test_reject_happy_path(): void
    {
        $res = $this->ok('smartalloc_manual_reject', ['entry_ids' => [123], 'reason_code' => 'duplicate']);
        $this->assertContains($res['status'], [200, 302]);
        $this->assertStringContainsString('success', $res['body']);
    }
}
