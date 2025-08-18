<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

use SmartAlloc\Tests\AllocationServiceTest;

$report = [];
try {
    $test = new AllocationServiceTest();
    $test->testDuplicateStudent(); // یا تست موردنظر
    $report['status'] = 'success';
} catch (\InvalidArgumentException $e) {
    $report = [
        'status' => 'invalid',
        'error'  => $e->getMessage(),
    ];
} catch (\Throwable $e) {
    $report = [
        'status' => 'error',
        'error'  => $e->getMessage(),
        'trace'  => array_slice($e->getTrace(), 0, 3),
    ];
}
file_put_contents(__DIR__ . '/allocation-test-debug.json', json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Wrote debug JSON.\n";
