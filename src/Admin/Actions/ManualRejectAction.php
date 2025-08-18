<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Actions;

use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Infra\Logger\NullLogger;

final class ManualRejectAction
{
    private const ALLOWED_REASONS = ['duplicate', 'ineligible', 'other'];

    public static function handle(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_send_json_error(['error' => 'forbidden'], 403);
        }

        check_ajax_referer('smartalloc_manual_action', 'nonce');

        $entryIds = isset($_POST['entry_ids']) ? (array) $_POST['entry_ids'] : [];
        $entryIds = array_map('absint', $entryIds);
        $reason   = sanitize_key((string) ($_POST['reason_code'] ?? ''));
        $notes    = isset($_POST['notes']) ? sanitize_textarea_field((string) $_POST['notes']) : null;

        if (!in_array($reason, self::ALLOWED_REASONS, true)) {
            wp_send_json_error(['error' => 'invalid_reason']);
        }

        $repo = apply_filters('smartalloc_allocations_repository', null);
        if (!$repo instanceof AllocationsRepository) {
            global $smartalloc_repo;
            if (is_object($smartalloc_repo)) {
                $repo = $smartalloc_repo;
            } else {
                global $wpdb;
                $repo = new AllocationsRepository(new NullLogger(), $wpdb);
            }
        }

        $reviewerId = (int) get_current_user_id();
        foreach ($entryIds as $id) {
            $repo->rejectManual($id, $reviewerId, $reason, $notes);
        }

        wp_send_json_success(['count' => count($entryIds)]);
    }
}
