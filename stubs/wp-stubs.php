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

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
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

