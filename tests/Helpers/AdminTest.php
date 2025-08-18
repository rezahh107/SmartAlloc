<?php

use Brain\Monkey\Functions;

if (!function_exists('makeNonce')) {
    function makeNonce(string $action): string {
        Functions\when('check_admin_referer')->alias(function (string $expected = '-1', string $query_arg = '_wpnonce') use ($action) {
            $nonce = $_REQUEST[$query_arg] ?? '';
            return $expected === $action && $nonce === 'sa-test-nonce';
        });
        return 'sa-test-nonce';
    }
}

if (!function_exists('withCapability')) {
    function withCapability(bool $allowed): void {
        Functions\when('current_user_can')->alias(function (string $cap) use ($allowed) {
            return $cap === (defined('SMARTALLOC_CAP') ? SMARTALLOC_CAP : 'manage_smartalloc') ? $allowed : false;
        });
    }
}

if (!function_exists('runAdminPost')) {
    function runAdminPost(string $action, array $post = [], array $get = []): array {
        $_POST = $post;
        $_GET = $get;
        $_REQUEST = array_merge($get, $post);

        $headers = [];
        $status = 200;
        $body   = '';

        if (function_exists('stub_wp_redirect')) {
            stub_wp_redirect(function ($location, $code) use (&$status, &$headers) {
                $status = $code;
                $headers[] = 'Location: ' . $location;
            });
        }

        if (function_exists('stub_wp_die')) {
            stub_wp_die(function ($message, $title, $args) use (&$status, &$body) {
                $status = $args['response'] ?? 500;
                $body   = is_string($message) ? $message : '';
            });
        }

        if (function_exists('stub_header')) {
            stub_header(function ($header) use (&$headers) {
                $headers[] = $header;
            });
        }

        if (function_exists('do_action')) {
            do_action('admin_post_' . $action);
        } elseif (function_exists($cb = 'admin_post_' . $action)) {
            $cb();
        }

        return ['status' => $status, 'body' => $body, 'headers' => $headers];
    }
}

if (!function_exists('renderPage')) {
    function renderPage(callable $renderer, array $get = [], array $post = []): string {
        $_GET = $get;
        $_POST = $post;
        $_REQUEST = array_merge($get, $post);
        ob_start();
        $renderer();
        return ob_get_clean();
    }
}

if (!class_exists('AdminTest')) {
    class AdminTest extends \PHPUnit\Framework\TestCase {
        public function test_placeholder(): void {
            $this->assertTrue(true);
        }
    }
}

