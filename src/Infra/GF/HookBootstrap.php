<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use SmartAlloc\Services\DbSafe;

final class HookBootstrap
{
    public static function registerEnabledForms(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'smartalloc_forms';
        $sql   = DbSafe::mustPrepare("SELECT form_id FROM {$table} WHERE status=%s", ['enabled']);
        $rows  = $wpdb->get_results($sql, ARRAY_A) ?: [];
        foreach ($rows as $r) {
            $f = (int) $r['form_id'];
            add_action("gform_after_submission_{$f}", [\SmartAlloc\Infra\GF\SabtSubmissionHandler::class, 'handle'], 10, 2);
        }
    }
}
