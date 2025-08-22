#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(0);
}

$root = dirname(__DIR__);

// Baseline defaults when a key is missing everywhere.
$configDefaults = [
    'rest_permission_violations' => 0,
    'sql_prepare_violations'    => 0,
    'secrets_findings'          => 0,
    'license_denied'            => 0,
    'i18n_domain_mismatches'    => 0,
    'coverage_pct_min'          => 0,
    'schema_warnings'           => 0,
    'require_manifest'          => true,
    'require_sbom'              => true,
    'version_mismatch_fatal'    => true,
    'pot_min_entries'           => 10,
    'dist_audit_max_errors'     => 0,
    'wporg_lint_max_warnings'   => 0,
];

// Collect CLI options dynamically for overrides.
$longOpts = ['enforce', 'profile:', 'junit'];
foreach (array_keys($configDefaults) as $k) {
    $longOpts[] = $k . ':';
}
$opts    = getopt('', $longOpts);
$enforce = getenv('RUN_ENFORCE') === '1' || isset($opts['enforce']);
$profile = $opts['profile'] ?? '';
$wantJUnit = isset($opts['junit']);

// Resolve profile file if provided.
$profilePath = null;
if ($profile !== '') {
    if ($profile === 'rc') {
        $profilePath = $root . '/scripts/.ga-enforce.rc.json';
    } elseif ($profile === 'ga') {
        $profilePath = $root . '/scripts/.ga-enforce.ga.json';
    } else {
        $profilePath = str_starts_with($profile, '/') ? $profile : $root . '/' . ltrim($profile, '/');
    }
}

$warnings = [];

// Load baseline file.
$config = $configDefaults;
$basePath = $root . '/scripts/.ga-enforce.json';
if (is_file($basePath)) {
    $data = json_decode((string)file_get_contents($basePath), true);
    if (is_array($data)) {
        $config = array_merge($config, $data);
    } else {
        $warnings[] = '.ga-enforce.json parse failed';
    }
} else {
    $warnings[] = '.ga-enforce.json missing, using defaults';
}

// Merge profile if any.
if ($profilePath !== null) {
    if (is_file($profilePath)) {
        $data = json_decode((string)file_get_contents($profilePath), true);
        if (is_array($data)) {
            $config = array_merge($config, $data);
        } else {
            $warnings[] = basename($profilePath) . ' parse failed';
        }
    } else {
        $warnings[] = basename($profilePath) . ' missing';
    }
}

// CLI overrides.
foreach ($configDefaults as $key => $def) {
    if (isset($opts[$key])) {
        $val = $opts[$key];
        if (is_bool($def)) {
            $config[$key] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
        } elseif (is_float($def)) {
            $config[$key] = (float)$val;
        } else {
            $config[$key] = (int)$val;
        }
    }
}

$signals = [];

// Helper functions.
function jsonCount(array $paths, string $label, array &$warnings): int
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

function jsonKeyCount(array $paths, string $label, string $key, array &$warnings): int
{
    foreach ($paths as $p) {
        if (is_file($p)) {
            $data = json_decode((string)file_get_contents($p), true);
            if (is_array($data)) {
                return (int)($data['summary'][$key] ?? 0);
            }
            $warnings[] = $label . ' parse failed';
            return 0;
        }
    }
    $warnings[] = $label . ' missing';
    return 0;
}

function readJsonFile(string $path, string $label, array &$warnings): ?array
{
    if (is_file($path)) {
        $data = json_decode((string)file_get_contents($path), true);
        if (is_array($data)) {
            return $data;
        }
        $warnings[] = $label . ' parse failed';
    } else {
        $warnings[] = $label . ' missing';
    }
    return null;
}

// qa-report and go-no-go are advisory.
readJsonFile($root . '/artifacts/qa/qa-report.json', 'qa-report.json', $warnings);
readJsonFile($root . '/artifacts/qa/go-no-go.json', 'go-no-go.json', $warnings);

// Scanner outputs
@passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/scan-rest-permissions.php') . ' --q');
$restData = readJsonFile($root . '/artifacts/security/rest-permissions.json', 'rest-permissions.json', $warnings);
$signals['rest_mutating_warnings'] = (int)($restData['summary']['mutating_warnings'] ?? 0);
$signals['rest_readonly_warnings'] = (int)($restData['summary']['readonly_warnings'] ?? 0);
$signals['rest_permission_violations'] = (int)($restData['summary']['warnings'] ?? 0);

@passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/scan-sql-prepare.php'));
$sqlData = readJsonFile($root . '/artifacts/security/sql-prepare.json', 'sql-prepare.json', $warnings);
$signals['sql_prepare_violations'] = (int)($sqlData['counts']['violations'] ?? 0);
$signals['sql_prepare_allowlisted'] = (int)($sqlData['counts']['allowlisted'] ?? 0);
$sqlViolationList = is_array($sqlData) ? ($sqlData['violations'] ?? []) : [];

@passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/scan-secrets.php'));
$secData = readJsonFile($root . '/artifacts/security/secrets.json', 'secrets.json', $warnings);
$signals['secrets_findings'] = (int)($secData['counts']['violations'] ?? 0);
$signals['secrets_allowlisted'] = (int)($secData['counts']['allowlisted'] ?? 0);

@passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/license-audit.php'));
$licData = readJsonFile($root . '/artifacts/compliance/license-audit.json', 'license-audit.json', $warnings);
$packages = is_array($licData) ? ($licData['packages'] ?? []) : [];
$signals['license_denied'] = count(array_filter($packages, static fn($p) => empty($p['approved'])));

// i18n
$pot = readJsonFile($root . '/artifacts/i18n/pot-refresh.json', 'pot-refresh.json', $warnings);
$signals['pot_entries'] = (int)($pot['pot_entries'] ?? 0);
$signals['i18n_domain_mismatches'] = (int)($pot['domain_mismatch'] ?? 0);

// manifest & sbom presence
$signals['manifest_present'] = is_file($root . '/artifacts/dist/manifest.json');
if (!$signals['manifest_present']) {
    $warnings[] = 'manifest.json missing';
}
$signals['sbom_present'] = is_file($root . '/artifacts/dist/sbom.json');
if (!$signals['sbom_present']) {
    $warnings[] = 'sbom.json missing';
}

// version coherence
$signals['version_mismatches'] = 0;
$vc = $root . '/scripts/version-coherence.php';
if (is_file($vc)) {
    $json = @shell_exec('php ' . escapeshellarg($vc));
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

// coverage
$signals['coverage_pct'] = null;
$covJson = $root . '/artifacts/coverage/coverage.json';
@passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/coverage-import.php'));
if (is_file($covJson)) {
    $data = json_decode((string)file_get_contents($covJson), true);
    if (is_array($data)) {
        $pct = $data['totals']['pct'] ?? null;
        if ($pct !== null) {
            $signals['coverage_pct'] = (float)$pct;
        } else {
            $warnings[] = 'coverage.json missing totals.pct';
        }
    } else {
        $warnings[] = 'coverage.json parse failed';
    }
} else {
    $warnings[] = 'coverage missing';
}

// dist audit
$signals['dist_audit_errors'] = null;
$distArtifact = $root . '/artifacts/dist/dist-audit.json';
$distScript   = $root . '/scripts/dist-audit.php';
$distData = null;
if (is_file($distArtifact)) {
    $distData = json_decode((string)file_get_contents($distArtifact), true);
} elseif (is_file($distScript)) {
    $json = @shell_exec('php ' . escapeshellarg($distScript));
    if ($json !== null) {
        $distData = json_decode($json, true);
    }
}
if (is_array($distData)) {
    $signals['dist_audit_errors'] = (int)($distData['summary']['violations'] ?? 0);
} else {
    $warnings[] = 'dist audit missing';
}

// wp.org lint
$signals['wporg_lint_warnings'] = null;
$lintArtifact = $root . '/artifacts/qa/wporg-lint.json';
$lintScript   = $root . '/scripts/wporg-lint.php';
$lintData = null;
if (is_file($lintArtifact)) {
    $lintData = json_decode((string)file_get_contents($lintArtifact), true);
} elseif (is_file($lintScript)) {
    $json = @shell_exec('php ' . escapeshellarg($lintScript));
    if ($json !== null) {
        $lintData = json_decode($json, true);
    }
}
if (is_array($lintData)) {
    $count = 0;
    $readme = $lintData['readme'] ?? [];
    if (!empty($readme['missing'])) {
        $count++;
    }
    $count += isset($readme['missing_headers']) && is_array($readme['missing_headers']) ? count($readme['missing_headers']) : 0;
    if (isset($readme['short_description']) && !$readme['short_description']) {
        $count++;
    }
    if (!empty($readme['sections']) && is_array($readme['sections'])) {
        foreach ($readme['sections'] as $lines) {
            if ($lines > 400) {
                $count++;
            }
        }
    }
    $assets = $lintData['assets'] ?? [];
    if (empty($assets['present'])) {
        $count++;
    } elseif (!empty($assets['files']) && is_array($assets['files'])) {
        foreach ($assets['files'] as $info) {
            if (!empty($info['missing']) || (isset($info['ok']) && !$info['ok'])) {
                $count++;
            }
        }
    }
    $signals['wporg_lint_warnings'] = $count;
} else {
    $warnings[] = 'wporg lint missing';
}

// schema validation
@passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/artifact-schema-validate.php'));
$schemaPath = $root . '/artifacts/schema/schema-validate.json';
$schemaWarn = null;
if (is_file($schemaPath)) {
    $schemaData = json_decode((string)file_get_contents($schemaPath), true);
    if (is_array($schemaData)) {
        $schemaWarn = (int)($schemaData['count'] ?? 0);
    } else {
        $warnings[] = 'schema-validate.json parse failed';
    }
} else {
    $warnings[] = 'schema-validate.json missing';
}
$signals['schema_warnings'] = $schemaWarn;

@passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/qa-report.php') . ' --q');

// Threshold checks
$failures = [];
if ($signals['rest_mutating_warnings'] > 0 || $signals['rest_readonly_warnings'] > (int)$config['rest_permission_violations']) {
    $failures[] = 'rest_permission_violations';
}
if ($signals['sql_prepare_violations'] > (int)$config['sql_prepare_violations']) {
    $failures[] = 'sql_prepare_violations';
}
if ($signals['secrets_findings'] > (int)$config['secrets_findings']) {
    $failures[] = 'secrets_findings';
}
if ($signals['license_denied'] > (int)$config['license_denied']) {
    $failures[] = 'license_denied';
}
if ($signals['i18n_domain_mismatches'] > (int)$config['i18n_domain_mismatches']) {
    $failures[] = 'i18n_domain_mismatches';
}
if ($signals['coverage_pct'] !== null && $signals['coverage_pct'] < (float)$config['coverage_pct_min']) {
    $failures[] = 'coverage_pct_min';
}
if ($signals['schema_warnings'] !== null && $signals['schema_warnings'] > (int)$config['schema_warnings']) {
    $failures[] = 'schema_warnings';
}
if ($signals['pot_entries'] < (int)$config['pot_min_entries']) {
    $failures[] = 'pot_min_entries';
}
if ($signals['dist_audit_errors'] !== null && $signals['dist_audit_errors'] > (int)$config['dist_audit_max_errors']) {
    $failures[] = 'dist_audit_errors';
}
if ($signals['wporg_lint_warnings'] !== null && $signals['wporg_lint_warnings'] > (int)$config['wporg_lint_max_warnings']) {
    $failures[] = 'wporg_lint_warnings';
}
if ($config['require_manifest'] && !$signals['manifest_present']) {
    $failures[] = 'manifest_missing';
}
if ($config['require_sbom'] && !$signals['sbom_present']) {
    $failures[] = 'sbom_missing';
}
if ($signals['version_mismatches'] > 0 && !empty($config['version_mismatch_fatal'])) {
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
    'signals'  => $signals,
    'failures' => $failures,
    'warnings' => $warnings,
    'verdict'  => $verdict,
];
ksort($out);

$gaDir = $root . '/artifacts/ga';
if (!is_dir($gaDir)) {
    mkdir($gaDir, 0777, true);
}
file_put_contents($gaDir . '/GA_ENFORCER.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$txt = [];
$txt[] = 'GA Enforcer Report';
$txt[] = 'Verdict: ' . $verdict;
$txt[] = 'REST warnings: mutating=' . $signals['rest_mutating_warnings'] . ' readonly=' . $signals['rest_readonly_warnings'];
$txt[] = 'SQL violations: ' . $signals['sql_prepare_violations'];
$txt[] = 'Secrets findings: ' . $signals['secrets_findings'] . ' allowlisted=' . $signals['secrets_allowlisted'];
$txt[] = 'License denied: ' . $signals['license_denied'];
$txt[] = 'i18n mismatches: ' . $signals['i18n_domain_mismatches'];
$txt[] = 'Coverage pct: ' . ($signals['coverage_pct'] ?? 'null');
$txt[] = 'Manifest present: ' . ($signals['manifest_present'] ? 'yes' : 'no');
$txt[] = 'SBOM present: ' . ($signals['sbom_present'] ? 'yes' : 'no');
$txt[] = 'Version mismatches: ' . $signals['version_mismatches'];
$txt[] = 'POT entries: ' . $signals['pot_entries'];
$txt[] = 'Dist audit errors: ' . ($signals['dist_audit_errors'] ?? 'null');
$txt[] = 'WP.org lint warnings: ' . ($signals['wporg_lint_warnings'] ?? 'null');
$txt[] = 'Schema warnings: ' . ($signals['schema_warnings'] ?? 'null');
file_put_contents($gaDir . '/GA_ENFORCER.txt', implode("\n", $txt) . "\n");

if ($wantJUnit) {
    $map = [
        'Version'       => 'version_mismatch',
        'Manifest'      => 'manifest_missing',
        'SBOM'          => 'sbom_missing',
        'Coverage'      => 'coverage_pct_min',
        'POT'           => 'pot_min_entries',
        'Dist-Audit'    => 'dist_audit_errors',
        'WP.org-Lint'   => 'wporg_lint_warnings',
        'i18n mismatches' => 'i18n_domain_mismatches',
    ];

    $suite = new SimpleXMLElement('<testsuite name="GA Enforcer"/>');
    $case = $suite->addChild('testcase');
    $case->addAttribute('name', 'REST.Permissions');
    if (!$enforce || ($opts['profile'] ?? '') !== 'ga') {
        $msg = 'mutating=' . $signals['rest_mutating_warnings'] . ' readonly=' . $signals['rest_readonly_warnings'];
        $sk = $case->addChild('skipped', htmlspecialchars($msg, ENT_QUOTES));
        $sk->addAttribute('message', $msg);
    } elseif ($signals['rest_mutating_warnings'] > 0 || $signals['rest_readonly_warnings'] > (int)$config['rest_permission_violations']) {
        $msg = 'mutating=' . $signals['rest_mutating_warnings'] . ' readonly=' . $signals['rest_readonly_warnings'];
        $fail = $case->addChild('failure', htmlspecialchars($msg, ENT_QUOTES));
        $fail->addAttribute('message', $msg);
    }

    $case = $suite->addChild('testcase');
    $case->addAttribute('name', 'Security.Secrets');
    if (!$enforce || ($opts['profile'] ?? '') !== 'ga') {
        $msg = 'violations=' . $signals['secrets_findings'] . ' allowlisted=' . $signals['secrets_allowlisted'];
        $sk = $case->addChild('skipped', htmlspecialchars($msg, ENT_QUOTES));
        $sk->addAttribute('message', $msg);
    } elseif ($signals['secrets_findings'] > 0) {
        $msg = 'secrets violations present';
        $fail = $case->addChild('failure', htmlspecialchars($msg, ENT_QUOTES));
        $fail->addAttribute('message', $msg);
    }

    $case = $suite->addChild('testcase');
    $case->addAttribute('name', 'Compliance.License');
    if (!$enforce || ($opts['profile'] ?? '') !== 'ga') {
        $msg = 'unapproved=' . $signals['license_denied'];
        $sk = $case->addChild('skipped', htmlspecialchars($msg, ENT_QUOTES));
        $sk->addAttribute('message', $msg);
    } elseif ($signals['license_denied'] > 0) {
        $msg = 'unapproved license found';
        $fail = $case->addChild('failure', htmlspecialchars($msg, ENT_QUOTES));
        $fail->addAttribute('message', $msg);
    }

    foreach ($map as $name => $key) {
        $case = $suite->addChild('testcase');
        $case->addAttribute('name', $name);
        if (in_array($key, $failures, true)) {
            $msg = $name . ' threshold exceeded';
            $fail = $case->addChild('failure', htmlspecialchars($msg, ENT_QUOTES));
            $fail->addAttribute('message', $msg);
        }
    }

    $case = $suite->addChild('testcase');
    $case->addAttribute('name', 'Artifacts.Schema');
    if (!$enforce) {
        $case->addChild('skipped');
    } elseif ($schemaWarn !== null && $schemaWarn > (int)$config['schema_warnings']) {
        $msg = 'schema warnings present';
        $fail = $case->addChild('failure', htmlspecialchars($msg, ENT_QUOTES));
        $fail->addAttribute('message', $msg);
    }

    $case = $suite->addChild('testcase');
    $case->addAttribute('name', 'SQL.Prepare');
    if (!$enforce || ($opts['profile'] ?? '') !== 'ga') {
        $msg = 'violations=' . $signals['sql_prepare_violations'] . ' allowlisted=' . $signals['sql_prepare_allowlisted'];
        $sk = $case->addChild('skipped', htmlspecialchars($msg, ENT_QUOTES));
        $sk->addAttribute('message', $msg);
    } elseif ($signals['sql_prepare_violations'] > 0) {
        $preview = [];
        foreach (array_slice($sqlViolationList, 0, 5) as $v) {
            if (isset($v['file'], $v['line'])) {
                $preview[] = $v['file'] . ':' . $v['line'];
            }
        }
        $msg = 'unprepared SQL in ' . implode(', ', $preview);
        $fail = $case->addChild('failure', htmlspecialchars($msg, ENT_QUOTES));
        $fail->addAttribute('message', $msg);
    }
    $dom = dom_import_simplexml($suite)->ownerDocument;
    $dom->formatOutput = true;
    file_put_contents($gaDir . '/GA_ENFORCER.junit.xml', $dom->saveXML());
}

exit($enforce && $verdict === 'FAIL' ? 1 : 0);

