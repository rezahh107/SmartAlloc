<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(0);
}

$root = dirname(__DIR__);
$coverageFile = $root . '/coverage-unit/index.xml';
$coverage = null;
$notes = [];

if (is_file($coverageFile)) {
    $xml = @simplexml_load_file($coverageFile);
    if ($xml !== false && isset($xml['line-rate'])) {
        $coverage = round(((float) $xml['line-rate']) * 100, 2);
    } else {
        $notes[] = 'coverage parse failed';
    }
} else {
    $notes[] = 'coverage missing';
}

$env = [
    'RUN_SECURITY_TESTS'    => getenv('RUN_SECURITY_TESTS') === '1',
    'RUN_PERFORMANCE_TESTS' => getenv('RUN_PERFORMANCE_TESTS') === '1',
    'E2E'                   => getenv('E2E') === '1',
];

$testFiles = 0;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests'));
foreach ($rii as $file) {
    if ($file->isFile() && substr($file->getFilename(), -8) === 'Test.php') {
        $testFiles++;
    }
}

$data = [
    'coverage_percent' => $coverage,
    'env' => $env,
    'test_files' => $testFiles,
    'notes' => $notes,
];

file_put_contents($root . '/qa-report.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

$html  = '<!DOCTYPE html><html dir="rtl"><meta charset="utf-8"><title>QA Report</title><body>';
$html .= '<h1>QA Report</h1><ul>';
$html .= '<li>Coverage: ' . ($coverage !== null ? $coverage . '%' : 'N/A') . '</li>';
$html .= '<li>Test files: ' . $testFiles . '</li>';
$html .= '<li>Env toggles:<ul>';
foreach ($env as $k => $v) {
    $html .= '<li>' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . ': ' . ($v ? 'on' : 'off') . '</li>';
}
$html .= '</ul></li>';
if ($notes) {
    $html .= '<li>Notes: ' . htmlspecialchars(implode(', ', $notes), ENT_QUOTES, 'UTF-8') . '</li>';
}
$html .= '</ul></body></html>';
file_put_contents($root . '/qa-report.html', $html);

exit(0);
