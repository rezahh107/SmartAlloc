<?php

declare(strict_types=1);

namespace SmartAlloc\REST\Controllers;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Services\ServiceContainer;
use SmartAlloc\Services\ExportService;

final class ExportController
{
    private ExportService $svc;

    public function __construct(?ExportService $svc = null)
    {
        $this->svc = $svc ?: ServiceContainer::export();
    }

    public function register(): void
    {
        $cb = function () {
            register_rest_route('smartalloc/v1', '/export', [
                'methods'             => 'POST',
                'permission_callback' => static fn() => \SmartAlloc\Security\CapManager::canManage(),
                'callback'            => function (\WP_REST_Request $request) {
                    if (!\SmartAlloc\Security\CapManager::canManage()) {
                        return new \WP_REST_Response(['error' => 'forbidden'], 403);
                    }

                    $formRaw = filter_input(INPUT_POST, 'form_id', FILTER_SANITIZE_NUMBER_INT);
                    $formRaw = $formRaw ?? $request->get_param('form_id');
                    $formId  = absint(wp_unslash((string) $formRaw));
                    if ($formId <= 0) {
                        return new \WP_REST_Response(['error' => 'invalid_form_id'], 400);
                    }

                    $nonceRaw = filter_input(INPUT_POST, '_wpnonce', FILTER_DEFAULT);
                    $nonceRaw = $nonceRaw ?? $request->get_param('_wpnonce');
                    $nonce    = sanitize_text_field(wp_unslash((string) $nonceRaw));
                    if (!wp_verify_nonce($nonce, 'smartalloc_export_' . $formId)) {
                        return new \WP_REST_Response(['error' => 'forbidden'], 403);
                    }

                    $payload = (array) $request->get_json_params();
                    $result  = $this->svc->export(new FormContext($formId), $payload);
                    return new \WP_REST_Response($result, 200);
                },
            ]);
        };
        add_action('rest_api_init', $cb);
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
            $cb();
        }
    }
}

