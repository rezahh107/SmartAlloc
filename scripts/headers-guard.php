#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Scan HTTP response headers and report missing security headers.
 * Advisory and deterministic; exits 0 always.
 */

const HG_REQUIRED_HEADERS = [
    'Content-Security-Policy',
    'Permissions-Policy',
    'Referrer-Policy',
    'X-Content-Type-Options',
    'X-Frame-Options',
];

/** Load allowlist entries from docs/SECURITY_HEADERS.md. */
function hg_load_allowlist(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return [];
    }
    $allow = [];
    $in = false;
    foreach ($lines as $line) {
        if (preg_match('/^##\s+Allowlist/i', $line)) {
            $in = true;
            continue;
        }
        if ($in && str_starts_with(trim($line), '##')) {
            break;
        }
        if ($in && preg_match('/^-\s*(.+)/', trim($line), $m)) {
            $allow[] = strtolower($m[1]);
        }
    }
    sort($allow);
    return $allow;
}

/** Analyse headers against required list. */
function hg_analyze(array $headers, array $allow): array
{
    $out = [];
    $present = 0;
    $missing = 0;
    $allowlisted = 0;
    foreach (HG_REQUIRED_HEADERS as $h) {
        $value = null;
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $h) === 0) {
                $value = trim((string)$v);
                break;
            }
        }
        $out[$h] = $value;
        if ($value !== null && $value !== '') {
            $present++;
        } elseif (in_array(strtolower($h), $allow, true)) {
            $allowlisted++;
        } else {
            $missing++;
        }
    }
    ksort($out);
    return [
        'headers' => $out,
        'counts' => [
            'present' => $present,
            'missing' => $missing,
            'allowlisted' => $allowlisted,
        ],
        'notes' => [],
    ];
}

/** Fetch headers via curl -I. */
function hg_fetch(string $url): array
{
    $raw = shell_exec('curl -fsI ' . escapeshellarg($url) . ' 2>/dev/null');
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $lines = preg_split('/\r?\n/', trim($raw));
    $headers = [];
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }
        [$k, $v] = explode(':', $line, 2);
        $headers[trim($k)] = trim($v);
    }
    return $headers;
}

/** Execute scan and emit report. */
function hg_scan(string $url, string $allowFile, string $outFile): array
{
    $allow = hg_load_allowlist($allowFile);
    $headers = hg_fetch($url);
    if ($headers === []) {
        $report = [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'headers' => [],
            'counts' => ['present' => 0, 'missing' => 0, 'allowlisted' => 0],
            'notes' => ['fetch_failed'],
        ];
    } else {
        $report = hg_analyze($headers, $allow);
        $report['generated_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
    }
    ksort($report);
    if (!is_dir(dirname($outFile))) {
        @mkdir(dirname($outFile), 0777, true);
    }
    file_put_contents($outFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return $report;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    $root = dirname(__DIR__);
    $opts = getopt('', ['url::','allowlist::','output::','q']);
    $url = $opts['url'] ?? 'http://localhost';
    $allow = $opts['allowlist'] ?? ($root . '/docs/SECURITY_HEADERS.md');
    $out = $opts['output'] ?? ($root . '/artifacts/security/headers.json');
    $report = hg_scan($url, $allow, $out);
    if (!isset($opts['q'])) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    exit(0);
}
