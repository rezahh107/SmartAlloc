<?php

/**
 * QA Report Generator
 * Aggregates all test results and generates reports
 */

$options = getopt('', [
    'security:',
    'phpcs:',
    'unit:',
    'e2e:',
    'perf:',
    'health:',
    'out-json:',
    'out-md:',
    'out-friendly:'
]);

$report = [
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'gates' => [
        'security' => ['status' => 'PENDING', 'score' => 0],
        'performance' => ['status' => 'PENDING', 'score' => 0],
        'testing' => ['status' => 'PENDING', 'score' => 0],
        'wp_standards' => ['status' => 'PENDING', 'score' => 0],
        'patch_guard' => ['status' => 'PENDING', 'score' => 0],
        'site_health' => ['status' => 'PENDING', 'score' => 0]
    ],
    'overall_status' => 'PENDING'
];

// Process Security Gate
if (isset($options['security']) && file_exists($options['security'])) {
    $security = json_decode(file_get_contents($options['security']), true);
    $criticalOrHigh = 0;
    foreach ($security['advisories'] ?? [] as $advisory) {
        if (in_array($advisory['severity'], ['critical', 'high'])) {
            $criticalOrHigh++;
        }
    }
    $report['gates']['security'] = [
        'status' => $criticalOrHigh === 0 ? 'PASS' : 'FAIL',
        'score' => $criticalOrHigh === 0 ? 100 : 0,
        'details' => ['critical_high_count' => $criticalOrHigh]
    ];
}

// Process Testing Gate
if (isset($options['unit']) && file_exists($options['unit'])) {
    $unitXml = simplexml_load_file($options['unit']);
    $failures = (int)$unitXml->testsuite['failures'];
    $errors = (int)$unitXml->testsuite['errors'];
    $tests = (int)$unitXml->testsuite['tests'];

    $report['gates']['testing'] = [
        'status' => ($failures + $errors) === 0 ? 'PASS' : 'FAIL',
        'score' => $tests > 0 ? round(($tests - $failures - $errors) / $tests * 100) : 0,
        'details' => [
            'total_tests' => $tests,
            'failures' => $failures,
            'errors' => $errors
        ]
    ];
}

// Process Performance Gate
if (isset($options['perf']) && file_exists($options['perf'])) {
    $perf = json_decode(file_get_contents($options['perf']), true);
    $p95 = $perf['p95_ms'] ?? 1000;
    $report['gates']['performance'] = [
        'status' => $p95 < 500 ? 'PASS' : 'FAIL',
        'score' => min(100, round((1000 - $p95) / 10)),
        'details' => ['p95_ms' => $p95]
    ];
}

// Process WP Standards Gate
if (isset($options['phpcs']) && file_exists($options['phpcs'])) {
    $phpcs = json_decode(file_get_contents($options['phpcs']), true);
    $errors = $phpcs['totals']['errors'] ?? 0;
    $warnings = $phpcs['totals']['warnings'] ?? 0;

    $report['gates']['wp_standards'] = [
        'status' => $errors === 0 ? 'PASS' : 'FAIL',
        'score' => max(0, 100 - ($errors * 10) - ($warnings * 2)),
        'details' => [
            'errors' => $errors,
            'warnings' => $warnings
        ]
    ];
}

// Process Site Health Gate
if (isset($options['health']) && file_exists($options['health'])) {
    $health = json_decode(file_get_contents($options['health']), true);
    $report['gates']['site_health'] = [
        'status' => $health['status'] === 'good' ? 'PASS' : 'FAIL',
        'score' => $health['score'] ?? 0,
        'details' => $health
    ];
}

// Determine overall status
$allPassed = true;
foreach ($report['gates'] as $gate) {
    if ($gate['status'] !== 'PASS') {
        $allPassed = false;
        break;
    }
}
$report['overall_status'] = $allPassed ? 'PASS' : 'FAIL';

// Write JSON report
if (isset($options['out-json'])) {
    file_put_contents($options['out-json'], json_encode($report, JSON_PRETTY_PRINT));
}

// Write Markdown summary
if (isset($options['out-md'])) {
    $md = "# QA Report\n\n";
    $md .= "**Generated**: {$report['timestamp']}\n";
    $md .= "**Overall Status**: {$report['overall_status']}\n\n";
    $md .= "## Gates Summary\n\n";
    $md .= "| Gate | Status | Score | Details |\n";
    $md .= "|------|--------|-------|---------|\n";

    foreach ($report['gates'] as $name => $gate) {
        $status = $gate['status'] === 'PASS' ? '✅' : '❌';
        $details = json_encode($gate['details'] ?? []);
        $md .= "| $name | $status {$gate['status']} | {$gate['score']}/100 | $details |\n";
    }

    file_put_contents($options['out-md'], $md);
}

// Write friendly report
if (isset($options['out-friendly'])) {
    $friendly = "سلام رضا جان! 🌟\n\n";
    $friendly .= $allPassed
        ? "خبر خوب! همه تست‌ها پاس شدن و سیستم آماده است. ✅\n"
        : "متاسفانه برخی تست‌ها رد شدن و نیاز به بررسی دارن. ❌\n\n";

    $friendly .= "وضعیت دروازه‌ها:\n";
    foreach ($report['gates'] as $name => $gate) {
        $emoji = $gate['status'] === 'PASS' ? '✅' : '❌';
        $friendly .= "- $name: $emoji\n";
    }

    file_put_contents($options['out-friendly'], $friendly);
}

exit($allPassed ? 0 : 1);
