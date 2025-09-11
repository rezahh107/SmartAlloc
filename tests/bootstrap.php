<?php
/** Test bootstrap */

date_default_timezone_set('UTC');

define( 'WP_PHPUNIT__DIR', dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit' );


require dirname(__DIR__) . '/vendor/autoload.php';
define( 'WP_TESTS_CONFIG_FILE_PATH', dirname( __DIR__ ) . '/wp-tests-config.php' );
require WP_PHPUNIT__DIR . '/includes/bootstrap.php';
