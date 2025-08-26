<?php

declare(strict_types=1);

namespace SmartAlloc\Http\Ajax;

use SmartAlloc\Http\Rest\AllocationController;
use WP_Error;
use WP_REST_Request;

/**
 * AJAX action wrapper for allocation endpoint.
 *
 * @phpcs:ignoreFile
 */
final class AllocationAction
{
    public function __construct(private AllocationController $controller) {}

    public function register(): void
    {
        add_action('wp_ajax_smartalloc_allocate', [$this, 'handle']);
    }

    public function handle(): void
    {
        $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING);
        $nonce = is_null($nonce) ? '' : wp_unslash($nonce);
        if (!wp_verify_nonce($nonce, 'smartalloc_allocate') || !current_user_can('smartalloc_manage')) {
            wp_send_json_error('forbidden', 403);
        }

        $request = new WP_REST_Request('POST', '/');
        $payload = filter_input(INPUT_POST, 'payload', FILTER_UNSAFE_RAW);
        $request->set_body((string) ($payload === null ? '' : wp_unslash($payload)));
        $response = $this->controller->handle($request);
        if ($response instanceof WP_Error) {
            wp_send_json_error($response->get_error_message(), 400);
        }
        wp_send_json_success($response);
    }
}
