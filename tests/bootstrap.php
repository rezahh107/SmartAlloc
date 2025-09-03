<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../stubs/wp-stubs.php';
require_once __DIR__ . '/_support/ReproBuilderStub.php';
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/..');
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}
