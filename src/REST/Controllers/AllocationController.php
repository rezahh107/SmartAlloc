<?php

declare(strict_types=1);

namespace SmartAlloc\REST\Controllers;

use SmartAlloc\Contracts\AllocationServiceInterface;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Security\RestValidator;
use SmartAlloc\Services\Exceptions\DuplicateAllocationException;
use SmartAlloc\Services\Exceptions\InsufficientCapacityException;
use SmartAlloc\Services\Exceptions\InvalidFormContextException;
use SmartAlloc\Services\ServiceContainer;

final class AllocationController
{
    private AllocationServiceInterface $svc;

    public function __construct(?AllocationServiceInterface $svc = null)
    {
        $this->svc = $svc ?: ServiceContainer::allocation();
    }

    public function register(): void
    {
        $cb = function () {
            register_rest_route('smartalloc/v1', '/allocate/(?P<form_id>\d+)', [
                'methods'             => 'POST',
                'permission_callback' => fn() => RestValidator::check('manage_options', 'smartalloc_action'),
                'callback'            => function (\WP_REST_Request $request) {
                    $data   = RestValidator::sanitize($request->get_params(), ['form_id' => 'int']);
                    $formId = $data['form_id'] ?? 0;
                    if ($formId <= 0) {
                        return new \WP_REST_Response(['error' => 'invalid_form_id'], 400);
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
        };
        add_action('rest_api_init', $cb);
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
            $cb();
        }
    }
}
