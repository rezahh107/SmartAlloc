<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'qa' => $root . '/qa-report.json',
    'rest' => $root . '/rest-violations.json',
    'sql' => $root . '/sql-violations.json',
    'secrets' => $root . '/secrets.json',
    'licenses' => $root . '/licenses.json',
    'manifest' => $root . '/artifacts/dist/manifest.json',
    'sbom' => $root . '/artifacts/dist/sbom.json',
];

$inputs = [];
foreach ($files as $key => $path) {
    if (is_file($path)) {
        $decoded = json_decode((string)file_get_contents($path), true);
        $inputs[$key] = $decoded;
    }
}

$options = getopt('', ['coverage-min::']);
$coverageMin = isset($options['coverage-min']) ? (float)$options['coverage-min'] : (float)(getenv('GNG_COVERAGE_MIN') ?: 85);

$severity = static function(string $env, string $default): string {
    $val = getenv($env);
    if ($val === false || $val === '') {
        return $default;
    }
    $val = strtolower($val);
    return in_array($val, ['high','medium','low'], true) ? $val : $default;
};

$sevRest = $severity('GNG_REST_SEVERITY', 'high');
$sevSql = $severity('GNG_SQL_SEVERITY', 'high');
$sevLicense = $severity('GNG_LICENSE_SEVERITY', 'high');
$sevSecrets = $severity('GNG_SECRETS_SEVERITY', 'medium');
$sevCoverage = $severity('GNG_COVERAGE_SEVERITY', 'medium');
$sevVersion = $severity('GNG_VERSION_MISMATCH_SEVERITY', 'high');

$reasons = [];

if (isset($inputs['rest']) && is_array($inputs['rest'])) {
    $count = count($inputs['rest']);
    $inputs['rest'] = ['count' => $count];
    if ($count > 0) {
        $reasons[] = ['type' => 'rest_violations', 'count' => $count, 'severity' => $sevRest];
    }
}

if (isset($inputs['sql']) && is_array($inputs['sql'])) {
    $count = count($inputs['sql']);
    $inputs['sql'] = ['count' => $count];
    if ($count > 0) {
        $reasons[] = ['type' => 'sql_violations', 'count' => $count, 'severity' => $sevSql];
    }
}

if (isset($inputs['licenses']['summary']['denied'])) {
    $denied = (int)$inputs['licenses']['summary']['denied'];
    $inputs['licenses'] = ['denied' => $denied];
    if ($denied > 0) {
        $reasons[] = ['type' => 'license_denied', 'count' => $denied, 'severity' => $sevLicense];
    }
}

if (isset($inputs['secrets']) && is_array($inputs['secrets'])) {
    $count = count($inputs['secrets']);
    $inputs['secrets'] = ['count' => $count];
    if ($count > 0) {
        $reasons[] = ['type' => 'secrets_found', 'count' => $count, 'severity' => $sevSecrets];
    }
}

if (isset($inputs['qa']['coverage_percent'])) {
    $cov = (float)$inputs['qa']['coverage_percent'];
    $inputs['qa'] = ['coverage_percent' => $cov];
    if ($cov < $coverageMin) {
        $reasons[] = ['type' => 'coverage_below_threshold', 'coverage' => $cov, 'threshold' => $coverageMin, 'severity' => $sevCoverage];
    }
}

$vcScript = $root . '/scripts/version-coherence.php';
if (is_file($vcScript)) {
    $json = @shell_exec('php ' . escapeshellarg($vcScript));
    $decoded = json_decode((string)$json, true);
    $inputs['version_coherence'] = $decoded;
    $mismatches = $decoded['summary']['mismatches'] ?? [];
    if (!empty($mismatches)) {
        $reasons[] = ['type' => 'version_mismatch', 'details' => $mismatches, 'severity' => $sevVersion];
    }
}

$go = true;
foreach ($reasons as $r) {
    if (($r['severity'] ?? '') === 'high') {
        $go = false;
        break;
    }
}

$result = [
    'summary' => [
        'go' => $go,
        'reasons' => $reasons,
    ],
    'inputs' => $inputs,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

$dir = $root . '/artifacts/qa';
if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
}
$html = '<!DOCTYPE html><html dir="rtl"><meta charset="utf-8"><title>GO/NO-GO</title><body>';
$html .= '<h1>' . ($go ? 'GO' : 'NO-GO') . '</h1>';
if ($reasons) {
    $html .= '<ul>';
    foreach ($reasons as $r) {
        $msg = htmlspecialchars($r['type'] . ' (' . $r['severity'] . ')', ENT_QUOTES, 'UTF-8');
        $html .= '<li>' . $msg . '</li>';
    }
    $html .= '</ul>';
}
$html .= '</body></html>';
file_put_contents($dir . '/go-no-go.html', $html);

exit(0);
