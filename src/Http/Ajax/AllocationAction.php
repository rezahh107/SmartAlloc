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
        $nonce = $_POST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'smartalloc_allocate') || !current_user_can('manage_smartalloc')) {
            wp_send_json_error('forbidden', 403);
        }

        $request = new WP_REST_Request('POST', '/');
        $request->set_body((string) ($_POST['payload'] ?? ''));
        $response = $this->controller->handle($request);
        if ($response instanceof WP_Error) {
            wp_send_json_error($response->get_error_message(), 400);
        }
        wp_send_json_success($response);
    }
}
