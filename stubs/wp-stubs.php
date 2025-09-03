<?php
// WordPress function stubs for tests and static analysis.

if (!function_exists('apply_filters')) {
    /**
     * @param string $hook
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    function apply_filters($hook, $value, ...$args) {
        return $value;
    }
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}


if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['sa_options'][$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        $GLOBALS['sa_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        $base = $GLOBALS['wp_upload_dir_basedir'] ?? sys_get_temp_dir();
        return [
            'path' => $base,
            'url' => $base,
            'basedir' => $base,
            'baseurl' => $base,
        ];
    }
}

if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public array $mentors = [];
        public function prepare(string $query, ...$args): string {
            if (isset($args[0]) && is_array($args[0])) {
                $args = $args[0];
            }
            return vsprintf($query, $args);
        }
        public function query($query) { return 0; }
        public function get_var($query) { return 0; }
        public function get_results($query, $output = OBJECT) { return []; }
    }
    // make a global instance like WordPress does
    $GLOBALS['wpdb'] = new wpdb();
}
if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void { /* no-op stub */ }
}
if (!function_exists('__return_true')) {
    function __return_true() { return true; }
}

if (!function_exists('rest_get_server')) {
    function rest_get_server() {
        return new class {
            public function get_routes(): array {
                return [
                    '/smartalloc/v1/metrics' => [
                        ['permission_callback' => '__return_true'],
                    ],
                    '/smartalloc/v1/export' => [
                        ['permission_callback' => '__return_true'],
                    ],
                ];
            }
        };
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        public function __construct(public string $method = 'GET', public string $route = '') {}
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user(int $id): void {
        $GLOBALS['wp_current_user_id'] = $id;
    }
}

if (!function_exists('rest_do_request')) {
    function rest_do_request($request) {
        $id = $GLOBALS['wp_current_user_id'] ?? 0;
        $role = $GLOBALS['wp_user_roles'][$id] ?? '';
        $status = $role === 'administrator' ? 200 : 403;
        return new class($status) {
            public function __construct(private int $status) {}
            public function get_status(): int { return $this->status; }
        };
    }
}

if (!class_exists('WP_UnitTestCase')) {
    abstract class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
        protected object $factory;

        protected function setUp(): void {
            parent::setUp();
            if (method_exists($this, 'erisSetup')) {
                $this->erisSetup();
            }
            $this->factory = new class {
                public object $user;
                public function __construct() {
                    $this->user = new class {
                        public function create_and_get(array $args) {
                            $id = ($GLOBALS['wp_last_user_id'] ?? 0) + 1;
                            $GLOBALS['wp_last_user_id'] = $id;
                            $role = $args['role'] ?? '';
                            $GLOBALS['wp_user_roles'][$id] = $role;
                            return (object) ['ID' => $id];
                        }
                    };
                }
            };
        }

        public function name(): string
        {
            return $this->getName();
        }
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir): bool {
        if (!is_dir($dir)) {
            return mkdir($dir, 0777, true);
        }
        return true;
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        if ($type === 'mysql') {
            return gmdate('Y-m-d H:i:s');
        }
        return time();
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return is_string($text) ? $text : '';
    }
}

