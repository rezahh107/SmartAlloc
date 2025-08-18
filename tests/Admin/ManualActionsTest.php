<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Admin\Actions\{ManualApproveAction, ManualAssignAction, ManualRejectAction};
use SmartAlloc\Domain\Allocation\AllocationResult;

final class ManualActionsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('sanitize_textarea_field')->alias(fn($v) => $v);
        Functions\when('sanitize_key')->alias(fn($v) => $v);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_ajax_referer')->alias(fn() => null);
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('wp_send_json_error')->alias(function($d){ throw new \RuntimeException('error:' . json_encode($d)); });
        Functions\when('wp_send_json_success')->alias(function($d){ throw new \RuntimeException('success:' . json_encode($d)); });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_approve_best_commits_when_capacity_available(): void
    {
        $GLOBALS['smartalloc_repo'] = new class {
            public array $entries = [1 => ['candidates' => [['mentor_id' => 10]]]];
            public function findByEntryId($id){ return $this->entries[$id]; }
            public function approveManual($e,$m,$r,$n){ return new AllocationResult(['committed'=>true]); }
        };
        $repo = $GLOBALS['smartalloc_repo'];
        $_POST = ['entry_ids' => [1], 'nonce' => 'x'];
        try { ManualApproveAction::handle(); } catch (\RuntimeException $e) { $out = $e->getMessage(); }
        $data = json_decode(substr($out,8), true);
        $this->assertTrue($data['results']['1']['committed']);
    }

    public function test_approve_best_fails_when_capacity_reached(): void
    {
        $GLOBALS['smartalloc_repo'] = new class {
            public array $entries = [1 => ['candidates' => [['mentor_id' => 10]]]];
            public function findByEntryId($id){ return $this->entries[$id]; }
            public function approveManual($e,$m,$r,$n){ return new AllocationResult(['committed'=>false,'reason'=>'capacity']); }
        };
        $repo = $GLOBALS['smartalloc_repo'];
        $_POST = ['entry_ids' => [1], 'nonce' => 'x'];
        try { ManualApproveAction::handle(); } catch (\RuntimeException $e) { $out = $e->getMessage(); }
        $data = json_decode(substr($out,8), true);
        $this->assertFalse($data['results']['1']['committed']);
    }

    public function test_assign_specific_mentor_commits_or_fails_by_capacity(): void
    {
        $GLOBALS['smartalloc_repo'] = new class {
            public array $calls = [];
            public function approveManual($e,$m,$r,$n){ $this->calls[] = [$e,$m]; return new AllocationResult(['committed'=>true]); }
        };
        $repo = $GLOBALS['smartalloc_repo'];
        $_POST = ['entry_ids' => [5], 'mentor_id' => 55, 'nonce' => 'x'];
        try { ManualAssignAction::handle(); } catch (\RuntimeException $e) { $out = $e->getMessage(); }
        $this->assertSame([5,55], $repo->calls[0]);
    }

    public function test_reject_sets_reason_and_timestamps(): void
    {
        $GLOBALS['smartalloc_repo'] = new class {
            public array $calls = [];
            public function rejectManual($e,$r,$reason,$n){ $this->calls[] = [$e,$reason]; }
        };
        $repo = $GLOBALS['smartalloc_repo'];
        $_POST = ['entry_ids'=>[2],'reason_code'=>'duplicate','nonce'=>'x'];
        try { ManualRejectAction::handle(); } catch (\RuntimeException $e) { $out = $e->getMessage(); }
        $this->assertSame([2,'duplicate'], $repo->calls[0]);
    }

    public function test_bulk_actions_apply_to_multiple_entries(): void
    {
        $GLOBALS['smartalloc_repo'] = new class {
            public array $calls = [];
            public function rejectManual($e,$r,$reason,$n){ $this->calls[] = $e; }
        };
        $repo = $GLOBALS['smartalloc_repo'];
        $_POST = ['entry_ids'=>[1,2,3],'reason_code'=>'other','nonce'=>'x'];
        try { ManualRejectAction::handle(); } catch (\RuntimeException $e) { $out = $e->getMessage(); }
        $this->assertCount(3, $repo->calls);
    }
}
