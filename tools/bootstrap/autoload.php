<?php

// Fail-fast Composer autoload
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException("Missing Composer autoload at $autoload");
}
require $autoload;

// Timezone & test-time helpers
require __DIR__ . '/time.php';

// Per-test Brain Monkey hooks via PHPUnit extension
require __DIR__ . '/mocks.php';

// Only for Integration (explicit opt-in)
require __DIR__ . '/environment.php';
