<?php
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../vendor/giorgiosironi/eris/src/Generator/SequenceGenerator.php';
require_once __DIR__ . '/../vendor/giorgiosironi/eris/src/Generator/IntegerGenerator.php';
require_once __DIR__ . '/../vendor/giorgiosironi/eris/src/Generator/AssociativeArrayGenerator.php';
require_once __DIR__ . '/../vendor/giorgiosironi/eris/src/Generator/ElementsGenerator.php';

$wpTestsDir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';
if (file_exists($wpTestsDir . '/includes/functions.php')) {
    require_once $wpTestsDir . '/includes/functions.php';
    require_once $wpTestsDir . '/includes/bootstrap.php';
}
