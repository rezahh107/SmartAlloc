<?php
// @security-ok-nonce
// @security-ok-rest

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
                'permission_callback' => [$this, 'permissions'],
                'callback' => [$this, 'handle'],
            ]);
        });
    }

    public function permissions(WP_REST_Request $request): bool
    {
        $ts = (int) $request->get_header('X-SmartAlloc-Timestamp');
        if (abs(time() - $ts) > 300) {
            return false;
        }
        $secret = Settings::getWebhookSecret();
        $sig = (string) $request->get_header('X-SmartAlloc-Signature');
        $expected = hash_hmac('sha256', $request->get_body() ?: '', $secret);
        return $sig !== '' && hash_equals($expected, $sig);
    }

    /** @return WP_Error|WP_REST_Response */
    public function handle(WP_REST_Request $request)
    {
        if ('application/json' !== strtolower((string) $request->get_header('Content-Type'))) {
            return new WP_Error('invalid_content_type', 'Invalid content type', ['status' => 415]);
        }
        $requestId = (string) $request->get_header('X-Request-Id');
        if ($requestId === '') {
            return new WP_Error('missing_request_id', __('Missing request id', 'smartalloc'), ['status' => 400]);
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

        // Replay protection
        $key = 'smartalloc_wh_' . md5($sig . '|' . $ts . '|' . $requestId);
        if (get_transient($key)) {
            return new WP_Error('replay', __('Duplicate request', 'smartalloc'), ['status' => 409]);
        }
        set_transient($key, 1, 10 * MINUTE_IN_SECONDS);

        // Rate limiting
        $ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP) ?: '0.0.0.0';
        $rateKey = 'smartalloc_rl_' . md5($secret . '|' . $ip);
        $hits = (int) get_transient($rateKey);
        if ($hits >= 60) {
            return new WP_Error('rate_limited', __('Too many requests', 'smartalloc'), ['status' => 429]);
        }
        set_transient($rateKey, $hits + 1, 5 * MINUTE_IN_SECONDS);
        return new WP_REST_Response(['ok' => true], 200);
    }
}
