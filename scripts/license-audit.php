#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Composer license audit.
 * Reads composer.lock and compares package licenses against the
 * approved SPDX identifiers listed in docs/COMPLIANCE.md.
 *
 * Output: artifacts/compliance/license-audit.json
 * Always exits 0 (advisory).
 */

/** Extract approved licenses from docs/COMPLIANCE.md. */
function sa_approved_licenses(string $root): array
{
    $doc = $root . '/docs/COMPLIANCE.md';
    if (!is_file($doc)) {
        return [];
    }
    $approved = [];
    foreach (file($doc) as $line) {
        if (preg_match('/^\s*-\s*([A-Za-z0-9\.-]+)\b/', $line, $m)) {
            $approved[] = $m[1];
        }
    }
    return $approved;
}

/**
 * Parse composer.lock and produce audit data.
 */
function sa_license_audit(string $root): array
{
    $lock = $root . '/composer.lock';
    $packages = [];
    if (is_file($lock)) {
        $data = json_decode((string)file_get_contents($lock), true);
        foreach (['packages', 'packages-dev'] as $section) {
            foreach ($data[$section] ?? [] as $pkg) {
                $licenses = $pkg['license'] ?? [];
                $licenses = is_array($licenses) ? $licenses : [$licenses];
                $license  = $licenses[0] ?? '';
                $packages[] = [
                    'name'    => $pkg['name'],
                    'version' => $pkg['version'] ?? '',
                    'license' => $license,
                ];
            }
        }
    }

    $approved = sa_approved_licenses($root);
    foreach ($packages as &$p) {
        $p['approved'] = $p['license'] !== '' && in_array($p['license'], $approved, true);
        ksort($p);
    }
    unset($p);

    usort($packages, static fn($a, $b) => [$a['name'], $a['version']] <=> [$b['name'], $b['version']]);

    $counts = [
        'total'      => count($packages),
        'unapproved' => count(array_filter($packages, static fn($p) => !$p['approved'])),
    ];

    $report = [
        'packages'        => $packages,
        'counts'          => $counts,
        'generated_at_utc'=> gmdate('c'),
    ];
    ksort($report);
    return $report;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $root = dirname(__DIR__);
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg[0] !== '-') {
            $p = realpath($arg);
            if ($p !== false) {
                $root = $p;
            }
        }
    }

    $report = sa_license_audit($root);
    $outDir = $root . '/artifacts/compliance';
    @mkdir($outDir, 0777, true);
    file_put_contents($outDir . '/license-audit.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

