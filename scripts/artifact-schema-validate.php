#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(0);
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

$root = dirname(__DIR__);
$artifacts = $root . '/artifacts';
$schemaDir = $artifacts . '/schema';
ensure_dir($schemaDir);

$warnings = [];

// coverage
$cov = $artifacts . '/coverage/coverage.json';
if (is_file($cov)) {
    $raw = (string)file_get_contents($cov);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $warnings[] = ['file' => substr($cov, strlen($root) + 1), 'reason' => 'invalid JSON'];
    } else {
        $rel = substr($cov, strlen($root) + 1);
        $tot = $data['totals']['pct'] ?? null;
        if (!is_numeric($tot)) {
            $warnings[] = ['file' => $rel, 'reason' => 'missing totals.pct'];
        }
        foreach ($data['files'] ?? [] as $idx => $f) {
            foreach (['path', 'lines_total', 'lines_covered', 'pct'] as $field) {
                if (!array_key_exists($field, $f)) {
                    $warnings[] = ['file' => $rel, 'reason' => "files[$idx].$field missing"];
                }
            }
        }
    }
}

// dist manifest
$manifest = $artifacts . '/dist/manifest.json';
if (is_file($manifest)) {
    $raw = (string)file_get_contents($manifest);
    $data = json_decode($raw, true);
    $rel = substr($manifest, strlen($root) + 1);
    if (!is_array($data)) {
        $warnings[] = ['file' => $rel, 'reason' => 'invalid JSON'];
    } else {
        $hasEntries = isset($data['entries']) && is_array($data['entries']) && $data['entries'] !== [];
        if (!$hasEntries) {
            $warnings[] = ['file' => $rel, 'reason' => 'missing entries'];
        } else {
            foreach ($data['entries'] as $i => $entry) {
                if (!is_string($entry['path'] ?? null)) {
                    $warnings[] = ['file' => $rel, 'reason' => "entries[$i].path missing"];
                }
                $sha = $entry['sha256'] ?? null;
                if (!is_string($sha) || preg_match('/\A[a-f0-9]{64}\z/i', $sha) !== 1) {
                    $warnings[] = ['file' => $rel, 'reason' => "entries[$i].sha256 invalid"];
                }
                if (!is_int($entry['size'] ?? null)) {
                    $warnings[] = ['file' => $rel, 'reason' => "entries[$i].size missing"];
                }
            }
        }
        if (!empty($data['files']) && is_array($data['files'])) {
            $warnings[] = ['file' => $rel, 'reason' => 'legacy files[] present; use entries[] as canonical'];
        }
    }
} else {
    $warnings[] = ['file' => substr($manifest, strlen($root) + 1), 'reason' => 'missing file'];
}

// qa and i18n JSON parseability
foreach (['qa', 'i18n'] as $dir) {
    $base = $artifacts . '/' . $dir;
    if (!is_dir($base)) {
        continue;
    }
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    foreach ($iter as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'json') {
            $path = $file->getPathname();
            $raw = (string)file_get_contents($path);
            json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $warnings[] = ['file' => substr($path, strlen($root) + 1), 'reason' => 'invalid JSON'];
            }
        }
    }
}

usort(
    $warnings,
    function (array $a, array $b): int {
        $cmp = strcmp($a['file'], $b['file']);
        return $cmp !== 0 ? $cmp : strcmp($a['reason'], $b['reason']);
    }
);

$out = [
    'warnings' => $warnings,
    'count' => count($warnings),
];
ksort($out);

$tmp = $schemaDir . '/schema-validate.json.tmp';
file_put_contents($tmp, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
rename($tmp, $schemaDir . '/schema-validate.json');

echo '[schema-validate] warnings=' . $out['count'] . "\n";
exit(0);
