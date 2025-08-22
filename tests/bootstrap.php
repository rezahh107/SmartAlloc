<?php

/**
 * PHPUnit bootstrap file for SmartAlloc tests
 */

define('PHPUNIT_RUNNING', true);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../stubs/wp-stubs.php';
require_once __DIR__ . '/BaseTestCase.php';
require_once __DIR__ . '/_support/FaultFlags.php';
require_once __DIR__ . '/_support/Gini.php';
require_once __DIR__ . '/Fixtures/MentorFactory.php';
require_once __DIR__ . '/Fixtures/StudentFactory.php';
require_once __DIR__ . '/Fixtures/CrosswalkFactory.php';
require_once __DIR__ . '/Fixtures/BulkDatasetBuilder.php';

// Ensure WP_DEBUG is enabled but errors are not displayed
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$saFailOnDeprecation = getenv('SA_FAIL_ON_DEPRECATION') !== '0';
$saErrorHandler = function ($severity, $message, $file, $line) use ($saFailOnDeprecation) {
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        if ($saFailOnDeprecation) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
        error_log('[DEPRECATED] ' . $message);
        return true;
    }
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
};
set_error_handler($saErrorHandler);
$GLOBALS['sa_test_error_handler'] = $saErrorHandler;

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
          private array $params = [];
          public function __construct(array $p = []) { $this->params = $p; }
          public function set_body(string $body): void { $this->body = $body; }
          public function get_body(): string { return $this->body; }
          public function set_header(string $k, string $v): void { $this->headers[strtolower($k)] = $v; }
          public function get_header(string $k): string { return $this->headers[strtolower($k)] ?? ''; }
          public function get_params(): array { return $this->params; }
          public function get_param(string $k) { return $this->params[$k] ?? null; }
          public function get_json_params(): array { return json_decode($this->body, true) ?: []; }
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

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
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

if (!function_exists('sa_tests_temp_dir')) {
    function sa_tests_temp_dir(string $prefix = 'sa'): string {
        $dir = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(4));
        if (!wp_mkdir_p($dir)) {
            throw new \RuntimeException('Failed to create temp dir');
        }
        return $dir;
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

// Load wpdb stub and ensure global is available
require_once __DIR__ . '/TestDoubles/WordPress/WpdbStub.php';
global $wpdb;
if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
    $wpdb = new wpdb();
}

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
if (!defined('SMARTALLOC_TEST_MODE')) {
    define('SMARTALLOC_TEST_MODE', true);
}
// === SMARTALLOC TEST FOUNDATION START ===
if (!defined('SMARTALLOC_TEST_FOUNDATION')) {
    define('SMARTALLOC_TEST_FOUNDATION', true);
    $patch = __DIR__ . '/../vendor/antecedent/patchwork/src/patchwork.php';
    if (file_exists($patch)) {
        require_once $patch;
    }
    foreach (['EnvReset', 'AdminTest', 'HttpTestCase', 'WpdbSpy'] as $h) {
        $p = __DIR__ . '/Helpers/' . $h . '.php';
        if (file_exists($p)) {
            require_once $p;
        }
    }
    \Brain\Monkey\Functions\when('wp_redirect')->alias(function ($location, $status = 302) {
        if (isset($GLOBALS['_sa_redirect_collector'])) {
            ($GLOBALS['_sa_redirect_collector'])($location, $status);
        }
        return true;
    });
    \Brain\Monkey\Functions\when('wp_die')->alias(function ($message = '', $title = '', $args = []) {
        if (isset($GLOBALS['_sa_die_collector'])) {
            ($GLOBALS['_sa_die_collector'])($message, $title, $args);
        }
        return '';
    });
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    set_error_handler($GLOBALS['sa_test_error_handler']);
}
// === SMARTALLOC TEST FOUNDATION END ===
