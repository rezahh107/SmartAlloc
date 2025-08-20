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

$restViolations = null;
$scanner = $root . '/scripts/scan-rest-permissions.php';
if (is_file($scanner)) {
    $json = @shell_exec('php ' . escapeshellarg($scanner));
    if ($json !== null) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $restViolations = count($decoded);
        } else {
            $notes[] = 'rest permission scan parse failed';
        }
    } else {
        $notes[] = 'rest permission scan failed';
    }
} else {
    $notes[] = 'rest permission scanner missing';
}

$sqlViolations = null;
$sqlFiles = [];
$sqlScanner = $root . '/scripts/scan-sql-prepare.php';
if (is_file($sqlScanner)) {
    $json = @shell_exec('php ' . escapeshellarg($sqlScanner));
    if ($json !== null) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $sqlViolations = count($decoded);
            foreach (array_slice($decoded, 0, 10) as $item) {
                if (isset($item['file'])) {
                    $sqlFiles[] = $item['file'];
                }
            }
        } else {
            $notes[] = 'sql prepare scan parse failed';
        }
    } else {
        $notes[] = 'sql prepare scan failed';
    }
} else {
    $notes[] = 'sql prepare scanner missing';
}

$data = [
    'coverage_percent' => $coverage,
    'env' => $env,
    'test_files' => $testFiles,
    'rest_permission_violations' => $restViolations,
    'sql_prepare_violations' => $sqlViolations,
    'notes' => $notes,
];

file_put_contents($root . '/qa-report.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

$html  = '<!DOCTYPE html><html dir="rtl"><meta charset="utf-8"><title>QA Report</title><body>';
$html .= '<h1>QA Report</h1><ul>';
$html .= '<li>Coverage: ' . ($coverage !== null ? $coverage . '%' : 'N/A') . '</li>';
$html .= '<li>Test files: ' . $testFiles . '</li>';
$html .= '<li>REST permission violations: ' . ($restViolations !== null ? $restViolations : 'N/A') . '</li>';
$html .= '<li>SQL prepare violations: ' . ($sqlViolations !== null ? $sqlViolations : 'N/A');
if ($sqlFiles) {
    $html .= '<ul>';
    foreach ($sqlFiles as $f) {
        $html .= '<li>' . htmlspecialchars($f, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $html .= '</ul>';
}
$html .= '</li>';
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
