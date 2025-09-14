<?php

// phpcs:ignoreFile

// ABSPATH only when running Integration suite
$integration = getenv('WP_INTEGRATION') === '1';
if (! $integration || defined('ABSPATH')) {
    return;
}

// Try common paths, override with WP_PATH env if provided
$paths = [];
if ($env = getenv('WP_PATH')) {
    $paths[] = rtrim($env, '/') . '/';
}
$paths[] = '/var/www/html/';
$paths[] = __DIR__ . '/../../wp/';
$paths[] = __DIR__ . '/../../';

foreach ($paths as $p) {
    if (is_file($p . 'wp-config-sample.php') || is_file($p . 'wp-settings.php')) {
        define('ABSPATH', $p);
        break;
    }
}

if (! defined('ABSPATH')) { // @phpstan-ignore-line
    throw new RuntimeException(
        'Could not resolve ABSPATH for Integration tests. Set WP_PATH or check your WP install.'
    );
}
