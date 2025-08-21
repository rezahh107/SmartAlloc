#!/usr/bin/env php
<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(0);
}

$root = dirname(__DIR__);
$opts = getopt('', ['enforce']);
$enforce = getenv('RUN_ENFORCE') === '1' || isset($opts['enforce']);

$configDefaults = [
    'rest_permission_violations' => 0,
    'sql_prepare_violations' => 0,
    'secrets_findings' => 0,
    'license_denied' => 0,
    'i18n_domain_mismatches' => 0,
    'coverage_min_lines_pct' => 0,
    'require_manifest' => true,
    'require_sbom' => true,
    'version_mismatch_fatal' => true,
];

$configPath = $root . '/scripts/.ga-enforce.json';
$config = $configDefaults;
$warnings = [];
if (is_file($configPath)) {
    $data = json_decode((string)file_get_contents($configPath), true);
    if (is_array($data)) {
        $config = array_merge($config, $data);
    } else {
        $warnings[] = '.ga-enforce.json parse failed';
    }
} else {
    $warnings[] = '.ga-enforce.json missing, using defaults';
}

$signals = [];

$qaReportPath = $root . '/artifacts/qa/qa-report.json';
$qaReport = [];
if (is_file($qaReportPath)) {
    $decoded = json_decode((string)file_get_contents($qaReportPath), true);
    if (is_array($decoded)) {
        $qaReport = $decoded;
    } else {
        $warnings[] = 'qa-report.json parse failed';
    }
} else {
    $warnings[] = 'qa-report.json missing';
}

$signals['coverage_percent'] = isset($qaReport['coverage_percent']) ? (float) $qaReport['coverage_percent'] : 0.0;
$signals['rest_permission_violations'] = isset($qaReport['rest_permission_violations']) ? (int) $qaReport['rest_permission_violations'] : 0;
$signals['sql_prepare_violations'] = isset($qaReport['sql_prepare_violations']) ? (int) $qaReport['sql_prepare_violations'] : 0;

$goNoGoPath = $root . '/artifacts/qa/go-no-go.json';
if (is_file($goNoGoPath)) {
    $tmp = json_decode((string)file_get_contents($goNoGoPath), true);
    if (!is_array($tmp)) {
        $warnings[] = 'go-no-go.json parse failed';
    }
} else {
    $warnings[] = 'go-no-go.json missing';
}

function countFromFile(array $paths, string $label, array &$warnings): int
{
    foreach ($paths as $p) {
        if (is_file($p)) {
            $data = json_decode((string)file_get_contents($p), true);
            if (is_array($data)) {
                return count($data);
            }
            $warnings[] = $label . ' parse failed';
            return 0;
        }
    }
    $warnings[] = $label . ' missing';
    return 0;
}

function countLicense(array $paths, string $label, array &$warnings): int
{
    foreach ($paths as $p) {
        if (is_file($p)) {
            $data = json_decode((string)file_get_contents($p), true);
            if (is_array($data)) {
                return (int) ($data['summary']['denied'] ?? 0);
            }
            $warnings[] = $label . ' parse failed';
            return 0;
        }
    }
    $warnings[] = $label . ' missing';
    return 0;
}

$scanners = [
    'rest' => [
        'script' => $root . '/scripts/scan-rest-permissions.php',
        'paths'  => [$root . '/artifacts/qa/rest-violations.json', $root . '/rest-violations.json'],
    ],
    'sql' => [
        'script' => $root . '/scripts/scan-sql-prepare.php',
        'paths'  => [$root . '/artifacts/qa/sql-violations.json', $root . '/sql-violations.json'],
    ],
    'secrets' => [
        'script' => $root . '/scripts/scan-secrets.php',
        'paths'  => [$root . '/artifacts/qa/secrets.json', $root . '/secrets.json'],
    ],
    'license' => [
        'script' => $root . '/scripts/license-audit.php',
        'paths'  => [$root . '/artifacts/qa/licenses.json', $root . '/licenses.json'],
    ],
];

foreach ($scanners as $key => $info) {
    if (!is_file($info['script'])) {
        $warnings[] = $key . ' scanner missing';
    }
}

$signals['rest_permission_violations'] = countFromFile($scanners['rest']['paths'], 'rest violations', $warnings);
$signals['sql_prepare_violations'] = countFromFile($scanners['sql']['paths'], 'sql violations', $warnings);
$signals['secrets_findings'] = countFromFile($scanners['secrets']['paths'], 'secrets findings', $warnings);
$signals['license_denied'] = countLicense($scanners['license']['paths'], 'license audit', $warnings);

$potPath = $root . '/artifacts/i18n/pot-refresh.json';
if (is_file($potPath)) {
    $data = json_decode((string)file_get_contents($potPath), true);
    if (is_array($data)) {
        $signals['pot_entries'] = (int) ($data['pot_entries'] ?? 0);
        $signals['i18n_domain_mismatches'] = (int) ($data['domain_mismatch'] ?? 0);
    } else {
        $warnings[] = 'pot-refresh.json parse failed';
        $signals['pot_entries'] = 0;
        $signals['i18n_domain_mismatches'] = 0;
    }
} else {
    $warnings[] = 'pot-refresh.json missing';
    $signals['pot_entries'] = 0;
    $signals['i18n_domain_mismatches'] = 0;
}

$manifestPath = $root . '/artifacts/dist/manifest.json';
$sbomPath = $root . '/artifacts/dist/sbom.json';
$signals['manifest_present'] = is_file($manifestPath);
$signals['sbom_present'] = is_file($sbomPath);
if (!$signals['manifest_present']) {
    $warnings[] = 'manifest.json missing';
}
if (!$signals['sbom_present']) {
    $warnings[] = 'sbom.json missing';
}

$vcPath = $root . '/scripts/version-coherence.php';
$signals['version_mismatches'] = 0;
if (is_file($vcPath)) {
    $json = @shell_exec('php ' . escapeshellarg($vcPath));
    if ($json !== null) {
        $data = json_decode($json, true);
        if (is_array($data)) {
            $signals['version_mismatches'] = count($data['summary']['mismatches'] ?? []);
        } else {
            $warnings[] = 'version coherence parse failed';
        }
    } else {
        $warnings[] = 'version coherence exec failed';
    }
} else {
    $warnings[] = 'version coherence script missing';
}

$failures = [];
if ($signals['rest_permission_violations'] > (int) $config['rest_permission_violations']) {
    $failures[] = 'rest_permission_violations';
}
if ($signals['sql_prepare_violations'] > (int) $config['sql_prepare_violations']) {
    $failures[] = 'sql_prepare_violations';
}
if ($signals['secrets_findings'] > (int) $config['secrets_findings']) {
    $failures[] = 'secrets_findings';
}
if ($signals['license_denied'] > (int) $config['license_denied']) {
    $failures[] = 'license_denied';
}
if ($signals['i18n_domain_mismatches'] > (int) $config['i18n_domain_mismatches']) {
    $failures[] = 'i18n_domain_mismatches';
}
if ($signals['coverage_percent'] < (float) $config['coverage_min_lines_pct']) {
    $failures[] = 'coverage_min_lines_pct';
}
if ($config['require_manifest'] && !$signals['manifest_present']) {
    $failures[] = 'manifest_missing';
}
if ($config['require_sbom'] && !$signals['sbom_present']) {
    $failures[] = 'sbom_missing';
}
if (!empty($signals['version_mismatches']) && !empty($config['version_mismatch_fatal'])) {
    $failures[] = 'version_mismatch';
}

$verdict = 'PASS';
if ($failures) {
    $verdict = 'FAIL';
} elseif ($warnings) {
    $verdict = 'WARN';
}

ksort($signals);
ksort($config);
$out = [
    'signals' => $signals,
    'failures' => $failures,
    'warnings' => $warnings,
    'verdict' => $verdict,
];
ksort($out);

$gaDir = $root . '/artifacts/ga';
if (!is_dir($gaDir)) {
    mkdir($gaDir, 0777, true);
}
file_put_contents($gaDir . '/GA_ENFORCER.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$txt  = "گزارش GA Enforcer\n";
$txt .= "نتیجه: $verdict\n";
$txt .= "تخطی‌های REST: {$signals['rest_permission_violations']}\n";
$txt .= "تخطی‌های SQL: {$signals['sql_prepare_violations']}\n";
$txt .= "رازهای یافت‌شده: {$signals['secrets_findings']}\n";
$txt .= "مجوزهای ردشده: {$signals['license_denied']}\n";
$txt .= "دامنه‌های i18n ناهماهنگ: {$signals['i18n_domain_mismatches']}\n";
$txt .= "پوشش خطوط: {$signals['coverage_percent']}%\n";
$txt .= "وجود manifest: " . ($signals['manifest_present'] ? 'بله' : 'خیر') . "\n";
$txt .= "وجود SBOM: " . ($signals['sbom_present'] ? 'بله' : 'خیر') . "\n";
$txt .= "ناسازگاری نسخه: {$signals['version_mismatches']}\n";
file_put_contents($gaDir . '/GA_ENFORCER.txt', $txt);

exit($enforce && $verdict === 'FAIL' ? 1 : 0);
