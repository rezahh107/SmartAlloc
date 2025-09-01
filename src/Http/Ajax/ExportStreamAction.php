<?php

declare(strict_types=1);

namespace SmartAlloc\Http\Ajax;

use SmartAlloc\Services\ExportService;
use WP_REST_Request;

final class ExportStreamAction
{
    public function __construct(private ExportService $svc) {}

    public function register(): void
    {
        add_action('wp_ajax_smartalloc_export_stream', [$this, 'handle']);
    }

    public function handle(): void
    {
        $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING);
        $nonce = is_null($nonce) ? '' : wp_unslash($nonce);
        if (!wp_verify_nonce($nonce, 'smartalloc_export_stream') || !current_user_can('smartalloc_manage')) {
            wp_send_json_error('forbidden', 403);
        }

        $filters = [];
        $limit = filter_input(INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT);
        if ($limit !== null) {
            $filters['limit'] = (string) wp_unslash($limit);
        }
        nocache_headers();
        $this->svc->streamExport($filters);
    }
}
