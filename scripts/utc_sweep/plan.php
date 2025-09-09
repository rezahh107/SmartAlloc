<?php

require_once __DIR__ . '/UTCSweepScanner.php';

use SmartAlloc\UTC\UTCSweepScanner;

$scanner = new UTCSweepScanner();
$results = $scanner->scan(dirname(__DIR__, 2));
file_put_contents('UTC_CANDIDATES.json', json_encode($results, JSON_PRETTY_PRINT));
