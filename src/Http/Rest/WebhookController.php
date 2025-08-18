<?php

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Infra\Settings\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class WebhookController
{
    public function register_routes(): void
    {
        add_action('rest_api_init', function (): void {
            if (!Settings::isIncomingWebhookEnabled()) {
                return;
            }
            register_rest_route('smartalloc/v1', '/hook/allocate', [
                'methods' => 'POST',
                'permission_callback' => '__return_true',
                'callback' => [$this, 'handle'],
            ]);
        });
    }

    /** @return WP_Error|WP_REST_Response */
    public function handle(WP_REST_Request $request)
    {
        if ('application/json' !== strtolower((string) $request->get_header('Content-Type'))) {
            return new WP_Error('invalid_content_type', 'Invalid content type', ['status' => 415]);
        }
        $ts = (int) $request->get_header('X-SmartAlloc-Timestamp');
        if (abs(time() - $ts) > 300) {
            return new WP_Error('invalid_timestamp', 'Timestamp skew', ['status' => 400]);
        }
        $secret = Settings::getWebhookSecret();
        $sig = (string) $request->get_header('X-SmartAlloc-Signature');
        $expected = hash_hmac('sha256', $request->get_body() ?: '', $secret);
        if (!$sig || !hash_equals($expected, $sig)) {
            return new WP_Error('invalid_signature', 'Signature verification failed', ['status' => 403]);
        }
        return new WP_REST_Response(['ok' => true], 200);
    }
}
