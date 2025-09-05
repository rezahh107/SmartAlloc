<?php

declare(strict_types=1);

namespace SmartAlloc\REST\Controllers;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Security\RestValidator;
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
                'permission_callback' => fn() => RestValidator::check('manage_options', 'smartalloc_action'),
                'callback'            => function (\WP_REST_Request $request) {
                    $data   = RestValidator::sanitize($request->get_params(), [
                        'form_id' => 'int',
                        'email'   => 'email',
                    ]);
                    $formId = $data['form_id'] ?? 0;
                    if ($formId <= 0) {
                        return new \WP_REST_Response(['error' => 'invalid_form_id'], 400);
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

