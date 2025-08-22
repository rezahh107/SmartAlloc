<?php

use Brain\Monkey\Functions;

if (!function_exists('makeNonce')) {
    function makeNonce(string $action): string {
        Functions\when('check_admin_referer')->alias(function (string $expected = '-1', string $query_arg = '_wpnonce') use ($action) {
            $nonce = $_REQUEST[$query_arg] ?? '';
            if ($expected === $action && $nonce === 'sa-test-nonce') {
                return true;
            }
            if (function_exists('wp_die')) {
                wp_die('forbidden', '', ['response' => 403]);
            }
            return false;
        });
        Functions\when('check_ajax_referer')->alias(function (string $expected, string $query_arg = 'nonce') use ($action) {
            $nonce = $_REQUEST[$query_arg] ?? '';
            if ($expected === $action && $nonce === 'sa-test-nonce') {
                return true;
            }
            if (function_exists('wp_die')) {
                wp_die('forbidden', '', ['response' => 403]);
            }
            return false;
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
                throw new \RuntimeException('_sa_wp_die');
            });
        }

        if (function_exists('stub_header')) {
            stub_header(function ($header) use (&$headers) {
                $headers[] = $header;
            });
        }

        ob_start();
        try {
            if (function_exists('do_action')) {
                do_action('admin_post_' . $action);
            }
            if (function_exists($cb = 'admin_post_' . $action)) {
                $cb();
            }
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '_sa_wp_die') {
                throw $e;
            }
        }
        $output = ob_get_clean();
        if ($body === '') {
            $body = $output;
        } else {
            $body .= $output;
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

use SmartAlloc\Tests\BaseTestCase;

if (!class_exists('AdminTest')) {
    class AdminTest extends BaseTestCase {
        public function test_placeholder(): void {
            $this->assertTrue(true);
        }
    }
}

