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
        if (!current_user_can('smartalloc_manage')) {
            wp_send_json_error(['error' => 'forbidden'], 403);
        }

        $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_STRING);
        $nonce = is_null($nonce) ? '' : wp_unslash($nonce);
        if (!wp_verify_nonce($nonce, 'smartalloc_manual_action')) {
            wp_send_json_error(['error' => 'invalid_nonce'], 400);
        }

        $entryIds = filter_input(INPUT_POST, 'entry_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $entryIds = is_array($entryIds) ? array_map('absint', wp_unslash($entryIds)) : [];
        $reason   = filter_input(INPUT_POST, 'reason_code', FILTER_SANITIZE_STRING);
        $reason   = is_null($reason) ? '' : sanitize_key(wp_unslash($reason));
        $notesRaw = filter_input(INPUT_POST, 'notes', FILTER_UNSAFE_RAW);
        $notes    = is_null($notesRaw) ? null : sanitize_textarea_field(wp_unslash($notesRaw));

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
