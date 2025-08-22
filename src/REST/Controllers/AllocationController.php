<?php

declare(strict_types=1);

namespace SmartAlloc\REST\Controllers;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Infra\DB\TableResolver;

final class AllocationController {
    public function __construct(private AllocationService $svc, private TableResolver $tables) {}

    public function register(): void {
        register_rest_route('smartalloc/v1', '/allocate', [
            'methods'  => 'POST',
            'permission_callback' => fn() => current_user_can('smartalloc_manage'),
            'callback' => function(\WP_REST_Request $req) {
                $formRaw = filter_input(INPUT_POST, 'form_id', FILTER_SANITIZE_NUMBER_INT);
                $formRaw = $formRaw ?? $req->get_param('form_id');
                $formId = (int) wp_unslash((string) $formRaw);
                if ($formId <= 0) return new \WP_Error('bad_request','form_id required');
                $ctx = new FormContext($formId);
                $students = (array) ($req->get_json_params()['students'] ?? []);
                $result = $this->svc->allocate($ctx, $students);
                return new \WP_REST_Response($result, 200);
            },
        ]);
    }
}
