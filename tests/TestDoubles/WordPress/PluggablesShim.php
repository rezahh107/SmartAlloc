<?php
// tests/TestDoubles/WordPress/PluggablesShim.php

declare(strict_types=1);

if (!\function_exists('nocache_headers')) {
    function nocache_headers(): void { /* no-op in tests */ }
}
if (!\function_exists('wp_redirect')) {
    function wp_redirect($location, $status = 302, $x = ''): bool { return true; }
}

