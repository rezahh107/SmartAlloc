<?php

/**
 * PHPUnit bootstrap file for SmartAlloc tests
 */

define('PHPUNIT_RUNNING', true);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../stubs/wp-stubs.php';
require_once __DIR__ . '/BaseTestCase.php';

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
        return gmdate('Y-m-d H:i:s');
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
        return mkdir($path, 0755, true);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
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
    define('SMARTALLOC_VERSION', '1.1.0');
}

if (!defined('SMARTALLOC_CAP')) {
    define('SMARTALLOC_CAP', 'manage_smartalloc');
}

if (!defined('SMARTALLOC_UPLOAD_DIR')) {
    define('SMARTALLOC_UPLOAD_DIR', 'smart-alloc');
} 
