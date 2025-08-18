<?php

/**
 * PHPUnit bootstrap file for SmartAlloc tests
 */

define('PHPUNIT_RUNNING', true);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../stubs/wp-stubs.php';
require_once __DIR__ . '/BaseTestCase.php';

// Ensure WP_DEBUG is enabled but errors are not displayed
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
ini_set('display_errors', '0');
error_reporting(E_ALL | E_STRICT);

set_error_handler(function ($severity, $message, $file, $line) {
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        // Log deprecations but do not throw.
        error_log('[DEPRECATED] ' . $message);
        return false;
    }
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

if (!function_exists('_doing_it_wrong')) {
    function _doing_it_wrong($function, $message, $version) {
        throw new \BadMethodCallException(
            sprintf('%s was called incorrectly. %s. This message was added in version %s.', $function, $message, $version)
        );
    }
}

// Mock WordPress functions for testing
// Simple in-memory caches for tests
$GLOBALS['sa_wp_cache'] = [];
$GLOBALS['sa_transients'] = [];

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '') {
        return $GLOBALS['sa_wp_cache'][$group][$key] ?? false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $value, $group = '', $ttl = 0) {
        $GLOBALS['sa_wp_cache'][$group][$key] = $value;
        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '') {
        unset($GLOBALS['sa_wp_cache'][$group][$key]);
        return true;
    }
}

  if (!function_exists('wp_cache_flush')) {
      function wp_cache_flush() {
          $GLOBALS['sa_wp_cache'] = [];
          return true;
      }
  }

  if (!function_exists('wp_upload_dir')) {
      function wp_upload_dir() {
          return ['basedir' => ($GLOBALS['wp_upload_dir_basedir'] ?? sys_get_temp_dir())];
      }
  }

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['sa_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $ttl = 0) {
        $GLOBALS['sa_transients'][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['sa_transients'][$key]);
        return true;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

  if (!function_exists('current_time')) {
      function current_time($type = 'mysql') {
          $format = $type === 'mysql' ? 'Y-m-d H:i:s' : $type;
          return gmdate($format);
      }
  }

  if (!class_exists('WP_REST_Request')) {
      class WP_REST_Request {
          private string $body = '';
          private array $headers = [];
          public function set_body(string $body): void { $this->body = $body; }
          public function get_body(): string { return $this->body; }
          public function set_header(string $k, string $v): void { $this->headers[strtolower($k)] = $v; }
          public function get_header(string $k): string { return $this->headers[strtolower($k)] ?? ''; }
          public function get_params(): array { return []; }
      }
  }

  if (!class_exists('WP_REST_Response')) {
      class WP_REST_Response {
          public function __construct(private array $data = [], private int $status = 200) {}
          public function get_data(): array { return $this->data; }
          public function get_status(): int { return $this->status; }
      }
  }

  if (!class_exists('WP_Error')) {
      class WP_Error {
          public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
          public function get_error_code(): string { return $this->code; }
          public function get_error_data(): array { return $this->data; }
      }
  }

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => sys_get_temp_dir(),
            'baseurl' => 'http://localhost'
        ];
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/') . '/';
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($path) {
        if (is_dir($path)) {
            return true;
        }
        return @mkdir($path, 0755, true);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($name) {
        return preg_replace('/[^A-Za-z0-9._-]/', '', $name);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = []) {
        return true;
    }
}

if (!function_exists('rgar')) {
    function rgar($array, $key, $default = '') {
        return $array[$key] ?? $default;
    }
}

// Mock global $wpdb
global $wpdb;
$wpdb = new class {
    public $prefix = 'wp_';
    public $last_error = '';
    public $insert_id = 0;
    public $rows_affected = 0;
    
    public function prepare($query, ...$args) {
        return $query;
    }
    
    public function query($query) {
        return true;
    }
    
    public function get_results($query, $output_type = 'OBJECT') {
        return [];
    }
    
    public function get_row($query, $output_type = 'OBJECT') {
        return null;
    }
    
    public function get_var($query) {
        return null;
    }
    
    public function insert($table, $data) {
        $this->insert_id = 1;
        return true;
    }
    
    public function update($table, $data, $where) {
        $this->rows_affected = 1;
        return true;
    }
    
    public function delete($table, $where) {
        $this->rows_affected = 1;
        return true;
    }
    
    public function replace($table, $data) {
        $this->insert_id = 1;
        return true;
    }
    
    public function get_charset_collate() {
        return 'DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
};

// Define constants if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('SMARTALLOC_VERSION')) {
    define('SMARTALLOC_VERSION', '1.0.0');
}
if (!defined('SMARTALLOC_DB_VERSION')) {
    define('SMARTALLOC_DB_VERSION', '1.0.0');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('SMARTALLOC_CAP')) {
    define('SMARTALLOC_CAP', 'manage_smartalloc');
}

if (!defined('SMARTALLOC_UPLOAD_DIR')) {
    define('SMARTALLOC_UPLOAD_DIR', 'smart-alloc');
}

if (!function_exists('sa_bootstrap_reset')) {
    function sa_bootstrap_reset(): void {
        $ref  = new ReflectionClass(\SmartAlloc\Bootstrap::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null);
    }
}
