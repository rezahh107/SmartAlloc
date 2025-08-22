#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Aggregate QA signals into deterministic JSON/HTML summaries.
 * Advisory by default; always exits 0.
 */

function qa_report(string $root, string $outDir): array
{
    $notes = [];

    $coverage = null;
    $covFile = $root . '/artifacts/coverage/coverage.json';
    if (is_file($covFile)) {
        $data = json_decode((string)file_get_contents($covFile), true);
        $coverage = (float)($data['totals']['lines']['pct'] ?? 0);
    } else {
        $notes[] = 'coverage missing';
    }

    $schemaWarnings = null;
    $schemaFile = $root . '/artifacts/schema/schema-validate.json';
    if (is_file($schemaFile)) {
        $data = json_decode((string)file_get_contents($schemaFile), true);
        if (is_array($data)) {
            $schemaWarnings = (int)($data['count'] ?? 0);
        }
    } else {
        $notes[] = 'schema report missing';
    }

    $rest = ['routes' => null, 'mutating_warnings' => null, 'readonly_warnings' => null];
    $restFile = $root . '/artifacts/security/rest-permissions.json';
    if (is_file($restFile)) {
        $data = json_decode((string)file_get_contents($restFile), true);
        if (is_array($data) && isset($data['summary'])) {
            $rest['routes'] = (int)($data['summary']['routes'] ?? 0);
            $rest['mutating_warnings'] = (int)($data['summary']['mutating_warnings'] ?? 0);
            $rest['readonly_warnings'] = (int)($data['summary']['readonly_warnings'] ?? 0);
        }
    } else {
        $notes[] = 'rest permissions missing';
    }

    $sql = ['violations' => null, 'allowlisted' => null];
    $sqlFile = $root . '/artifacts/security/sql-prepare.json';
    if (is_file($sqlFile)) {
        $data = json_decode((string)file_get_contents($sqlFile), true);
        if (is_array($data) && isset($data['counts'])) {
            $sql['violations'] = (int)($data['counts']['violations'] ?? 0);
            $sql['allowlisted'] = (int)($data['counts']['allowlisted'] ?? 0);
        }
    } else {
        $notes[] = 'sql prepare missing';
    }

    $secrets = ['violations' => null, 'allowlisted' => null];
    $secFile = $root . '/artifacts/security/secrets.json';
    if (is_file($secFile)) {
        $data = json_decode((string)file_get_contents($secFile), true);
        if (is_array($data) && isset($data['counts'])) {
            $secrets['violations'] = (int)($data['counts']['violations'] ?? 0);
            $secrets['allowlisted'] = (int)($data['counts']['allowlisted'] ?? 0);
        }
    } else {
        $notes[] = 'secrets report missing';
    }

    $headers = ['missing' => null, 'allowlisted' => null];
    $hdrFile = $root . '/artifacts/security/headers.json';
    if (is_file($hdrFile)) {
        $data = json_decode((string)file_get_contents($hdrFile), true);
        if (is_array($data) && isset($data['counts'])) {
            $headers['missing'] = (int)($data['counts']['missing'] ?? 0);
            $headers['allowlisted'] = (int)($data['counts']['allowlisted'] ?? 0);
        }
    } else {
        $notes[] = 'headers report missing';
    }

    $license = ['unapproved' => null];
    $licFile = $root . '/artifacts/compliance/license-audit.json';
    if (is_file($licFile)) {
        $data = json_decode((string)file_get_contents($licFile), true);
        if (is_array($data) && isset($data['counts'])) {
            $license['unapproved'] = (int)($data['counts']['unapproved'] ?? 0);
        }
    } else {
        $notes[] = 'license audit missing';
    }

    $exporter = ['errors' => null, 'warnings' => null];
    $expFile = $root . '/artifacts/exporter/validate.json';
    if (is_file($expFile)) {
        $data = json_decode((string)file_get_contents($expFile), true);
        if (is_array($data) && isset($data['counts'])) {
            $exporter['errors'] = (int)($data['counts']['errors'] ?? 0);
            $exporter['warnings'] = (int)($data['counts']['warnings'] ?? 0);
        }
    } else {
        $notes[] = 'exporter validation missing';
    }

    $validation = [
        'national_id_checksum'   => null,
        'mobile_prefix_09'       => null,
        'landline_eq_mobile'     => null,
        'duplicate_liaison_phone'=> null,
        'postal_code_fuzzy'      => ['accept' => null, 'manual' => null, 'reject' => null],
        'hikmat_tracking_sentinel' => null,
    ];
    $valFile = $root . '/artifacts/validation/form150.json';
    if (is_file($valFile)) {
        $data = json_decode((string)file_get_contents($valFile), true);
        if (is_array($data) && isset($data['violations'])) {
            $v = $data['violations'];
            $validation['national_id_checksum']    = isset($v['national_id_checksum']) ? (int)$v['national_id_checksum'] : 0;
            $validation['mobile_prefix_09']        = isset($v['mobile_prefix_09']) ? (int)$v['mobile_prefix_09'] : 0;
            $validation['landline_eq_mobile']      = isset($v['landline_eq_mobile']) ? (int)$v['landline_eq_mobile'] : 0;
            $validation['duplicate_liaison_phone'] = isset($v['duplicate_liaison_phone']) ? (int)$v['duplicate_liaison_phone'] : 0;
            if (isset($v['postal_code_fuzzy']) && is_array($v['postal_code_fuzzy'])) {
                $pc = $v['postal_code_fuzzy'];
                $validation['postal_code_fuzzy'] = [
                    'accept' => (int)($pc['accept'] ?? 0),
                    'manual' => (int)($pc['manual'] ?? 0),
                    'reject' => (int)($pc['reject'] ?? 0),
                ];
            }
            $validation['hikmat_tracking_sentinel'] = isset($v['hikmat_tracking_sentinel']) ? (int)$v['hikmat_tracking_sentinel'] : 0;
        }
    } else {
        $notes[] = 'form150 validation missing';
    }

    ksort($rest); ksort($sql); ksort($secrets); ksort($headers); ksort($license); ksort($exporter); ksort($validation);
    ksort($validation['postal_code_fuzzy']);

    $summary = [
        'coverage_pct' => $coverage,
        'schema_warnings' => $schemaWarnings,
        'rest_permissions' => $rest,
        'sql_prepare' => $sql,
        'secrets'     => $secrets,
        'headers'     => $headers,
        'license'     => $license,
        'exporter'    => $exporter,
        'validation'  => $validation,
    ];
    ksort($summary);

    sort($notes);

    $report = [
        'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'summary' => $summary,
        'notes' => $notes,
    ];
    ksort($report);

    if (!is_dir($outDir)) {
        @mkdir($outDir, 0777, true);
    }
    file_put_contents($outDir . '/qa-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $html = '<!DOCTYPE html><html dir="rtl"><meta charset="utf-8"><title>QA Report</title><body>';
    $html .= '<h1>QA Report</h1><ul>';
    $html .= '<li>Coverage: ' . ($coverage === null ? 'N/A' : $coverage) . '</li>';
    $html .= '<li>Schema warnings: ' . ($schemaWarnings === null ? 'N/A' : $schemaWarnings) . '</li>';
    $html .= '<li>REST mutating warnings: ' . ($rest['mutating_warnings'] ?? 'N/A') . '</li>';
    $html .= '<li>REST read-only warnings: ' . ($rest['readonly_warnings'] ?? 'N/A') . '</li>';
    $html .= '<li>SQL violations: ' . ($sql['violations'] ?? 'N/A') . ', allowlisted: ' . ($sql['allowlisted'] ?? 'N/A') . '</li>';
    $html .= '<li>Secret violations: ' . ($secrets['violations'] ?? 'N/A') . ', allowlisted: ' . ($secrets['allowlisted'] ?? 'N/A') . '</li>';
    $html .= '<li>Headers missing: ' . ($headers['missing'] ?? 'N/A') . ', allowlisted: ' . ($headers['allowlisted'] ?? 'N/A') . '</li>';
    $html .= '<li>Unapproved licenses: ' . ($license['unapproved'] ?? 'N/A') . '</li>';
    $html .= '<li>Exporter errors: ' . ($exporter['errors'] ?? 'N/A') . ', warnings: ' . ($exporter['warnings'] ?? 'N/A') . '</li>';
    $html .= '<li>Validation<ul>';
    $html .= '<li>National ID checksum: ' . ($validation['national_id_checksum'] ?? 'N/A') . '</li>';
    $html .= '<li>Mobile 09 prefix: ' . ($validation['mobile_prefix_09'] ?? 'N/A') . '</li>';
    $html .= '<li>Landline equals mobile: ' . ($validation['landline_eq_mobile'] ?? 'N/A') . '</li>';
    $html .= '<li>Duplicate liaison phone: ' . ($validation['duplicate_liaison_phone'] ?? 'N/A') . '</li>';
    $html .= '<li>Postal code fuzzy accept: ' . ($validation['postal_code_fuzzy']['accept'] ?? 'N/A') . ', manual: ' . ($validation['postal_code_fuzzy']['manual'] ?? 'N/A') . ', reject: ' . ($validation['postal_code_fuzzy']['reject'] ?? 'N/A') . '</li>';
    $html .= '<li>حکمة sentinel: ' . ($validation['hikmat_tracking_sentinel'] ?? 'N/A') . '</li>';
    $html .= '</ul></li>';
    if ($notes) {
        $html .= '<li>Notes<ul>';
        foreach ($notes as $n) {
            $html .= '<li>' . htmlspecialchars($n, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $html .= '</ul></li>';
    }
    $html .= '</ul></body></html>';
    file_put_contents($outDir . '/qa-report.html', $html);

    return $report;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    $root = dirname(__DIR__);
    $opts = getopt('', ['output::','q']);
    $outDir = $opts['output'] ?? ($root . '/artifacts/qa');
    $rep = qa_report($root, $outDir);
    if (!isset($opts['q'])) {
        echo json_encode($rep, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    exit(0);
}
