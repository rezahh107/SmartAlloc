<?php

// Register default WordPress function stubs for unit tests.
if (getenv('WP_INTEGRATION') === '1') {
    return;
}

require __DIR__ . '/../../tests/Support/WPFunctionStubs.php';
