<?php

namespace SmartAlloc\Tests\Support;

use Brain\Monkey\Functions;

if (getenv('WP_INTEGRATION') === '1') {
    return;
}

Functions\stubs([
    '__' => fn($text) => $text,
    '_e' => fn($text) => $text,
    'current_user_can' => fn() => true,
    'wp_die' => fn() => '',
    'get_option' => fn($name, $default = false) => $default,
]);
