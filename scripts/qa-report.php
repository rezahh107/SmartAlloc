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

    ksort($rest); ksort($sql); ksort($secrets); ksort($license);

    $summary = [
        'coverage_pct' => $coverage,
        'schema_warnings' => $schemaWarnings,
        'rest_permissions' => $rest,
        'sql_prepare' => $sql,
        'secrets'     => $secrets,
        'license'     => $license,
    ];
    ksort($summary);

    sort($notes);

    $report = [
        'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
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
    $html .= '<li>Unapproved licenses: ' . ($license['unapproved'] ?? 'N/A') . '</li>';
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

if (PHP_SAPI === 'cli' && realpath($argv[0]) === __FILE__) {
    $root = dirname(__DIR__);
    $opts = getopt('', ['output::','q']);
    $outDir = $opts['output'] ?? ($root . '/artifacts/qa');
    $rep = qa_report($root, $outDir);
    if (!isset($opts['q'])) {
        echo json_encode($rep, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    exit(0);
}
