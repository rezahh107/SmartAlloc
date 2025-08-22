<?php

declare(strict_types=1);

namespace SmartAlloc\REST\Controllers;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Exceptions\DuplicateAllocationException;
use SmartAlloc\Services\Exceptions\InsufficientCapacityException;
use SmartAlloc\Services\Exceptions\InvalidFormContextException;

final class AllocationController
{
    public function __construct(private AllocationService $svc) {}

    public function register(): void
    {
        register_rest_route('smartalloc/v1', '/allocate/(?P<form_id>\d+)?', [
            'methods'             => 'POST',
            'permission_callback' => static fn() => current_user_can('manage_smartalloc'),
            'callback'            => function (\WP_REST_Request $request) {
                $formRaw = filter_input(INPUT_POST, 'form_id', FILTER_SANITIZE_NUMBER_INT);
                $formRaw = $formRaw ?? $request->get_param('form_id');
                $formId  = (int) wp_unslash((string) $formRaw);
                if ($formId <= 0) {
                    return new \WP_REST_Response(['error' => 'invalid_form_id'], 400);
                }

                $nonce = (string) $request->get_param('_wpnonce');
                if (!wp_verify_nonce($nonce, 'smartalloc_allocate_' . $formId)) {
                    return new \WP_REST_Response(['error' => 'forbidden'], 403);
                }

                $payload = (array) $request->get_json_params();
                try {
                    $result = $this->svc->allocateWithContext(new FormContext($formId), $payload);
                    return new \WP_REST_Response($result, 201);
                } catch (DuplicateAllocationException $e) {
                    return new \WP_REST_Response(['error' => 'duplicate'], 409);
                } catch (InsufficientCapacityException $e) {
                    return new \WP_REST_Response(['error' => 'capacity'], 400);
                } catch (InvalidFormContextException $e) {
                    return new \WP_REST_Response(['error' => 'invalid_form'], 400);
                } catch (\Throwable $e) {
                    return new \WP_REST_Response(['error' => 'server_error'], 500);
                }
            },
        ]);
    }
}
