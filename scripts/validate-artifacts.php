#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(0);
}

$root = dirname(__DIR__);
$results = [];

$defs = [
    ['artifacts/qa/qa-report.json', function ($data) {
        if (!is_array($data)) {
            return ['not object'];
        }
        foreach ($data as $k => $v) {
            if (!is_int($v) && !is_float($v)) {
                return [$k . ' not numeric'];
            }
        }
        return [];
    }],
    ['artifacts/qa/go-no-go.json', function ($data) {
        return (is_array($data) && array_key_exists('verdict', $data)) ? [] : ['missing verdict'];
    }],
    ['artifacts/dist/manifest.json', function ($data) {
        $entries = $data['entries'] ?? $data;
        if (!is_array($entries)) {
            return ['not array'];
        }
        foreach ($entries as $i => $row) {
            if (
                !is_array($row)
                || !isset($row['path'], $row['size'], $row['sha256'])
                || !is_string($row['path'])
                || !is_string($row['sha256'])
                || !is_int($row['size'])
            ) {
                return ['invalid entry at ' . $i];
            }
        }
        return [];
    }],
    ['artifacts/dist/sbom.json', function ($data) {
        if (!is_array($data)) {
            return ['not array'];
        }
        foreach ($data as $i => $row) {
            if (!is_array($row) || !isset($row['name'], $row['version']) || !is_string($row['name']) || !is_string($row['version'])) {
                return ['invalid entry at ' . $i];
            }
            if (isset($row['license']) && !is_string($row['license'])) {
                return ['invalid license at ' . $i];
            }
        }
        return [];
    }],
    ['artifacts/i18n/pot-refresh.json', function ($data) {
        if (!is_array($data)) {
            return ['not object'];
        }
        if (!isset($data['pot_entries']) || !is_int($data['pot_entries'])) {
            return ['pot_entries missing'];
        }
        if (!isset($data['domain_mismatches']) || !is_int($data['domain_mismatches'])) {
            return ['domain_mismatches missing'];
        }
        return [];
    }],
];

foreach ($defs as [$rel, $validator]) {
    $path = $root . '/' . $rel;
    $entry = ['file' => $rel, 'ok' => false, 'errors' => []];
    if (!is_file($path)) {
        $entry['errors'][] = 'missing';
    } else {
        $data = json_decode((string)file_get_contents($path), true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $entry['errors'][] = 'parse error';
        } else {
            $errs = $validator($data);
            if ($errs) {
                $entry['errors'] = $errs;
            } else {
                $entry['ok'] = true;
            }
        }
    }
    ksort($entry);
    $results[] = $entry;
}

usort($results, fn($a, $b) => strcmp($a['file'], $b['file']));

$outDir = $root . '/artifacts/qa';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
file_put_contents($outDir . '/schema-validate.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$warn = 0;
foreach ($results as $r) {
    if (!$r['ok']) {
        $warn++;
    }
}
echo 'schema warnings: ' . $warn . "\n";

exit(0);
