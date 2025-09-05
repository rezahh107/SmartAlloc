<?php
// phpcs:ignoreFile
/** Foundation bootstrap. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Mocks/CompleteWpdbMock.php';
global $wpdb;
$wpdb = new \CompleteWpdbMock();
