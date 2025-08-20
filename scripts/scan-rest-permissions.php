#!/usr/bin/env php
<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(0);
}

$options = getopt('', ['allowlist-tag:']);
$allowTag = $options['allowlist-tag'] ?? '@security-ok-rest';

$root = dirname(__DIR__);
$files = collectPhpFiles($root);
$routes = findRestRoutes($files);
$violations = [];
foreach ($routes as $file => $calls) {
    $src = file_get_contents($file) ?: '';
    if (strpos($src, $allowTag) !== false) {
        continue;
    }
    foreach ($calls as $call) {
        if (!hasSecurePermissionCallback($call)) {
            $violations[] = $file;
            break;
        }
    }
}

echo json_encode($violations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(0);

function collectPhpFiles(string $root): array
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $out = [];
    foreach ($rii as $f) {
        if (
            $f->isFile() &&
            substr($f->getFilename(), -4) === '.php' &&
            strpos($f->getPathname(), '/tests/') === false &&
            strpos($f->getPathname(), '/vendor/') === false
        ) {
            $out[] = $f->getPathname();
        }
    }
    return $out;
}

/**
 * @param array<string> $files
 * @return array<string,array<int,string>>
 */
function findRestRoutes(array $files): array
{
    $out = [];
    foreach ($files as $file) {
        $src = file_get_contents($file) ?: '';
        $offset = 0;
        while (($pos = strpos($src, 'register_rest_route', $offset)) !== false) {
            $call = extractCall($src, $pos);
            if ($call !== null) {
                $out[$file][] = $call;
                $offset = $pos + 1;
            } else {
                break;
            }
        }
    }
    return $out;
}

function extractCall(string $src, int $start): ?string
{
    $open = strpos($src, '(', $start);
    if ($open === false) {
        return null;
    }
    $depth = 1;
    $i = $open + 1;
    $len = strlen($src);
    while ($i < $len && $depth > 0) {
        $ch = $src[$i];
        if ($ch === '(') {
            $depth++;
        } elseif ($ch === ')') {
            $depth--;
        }
        $i++;
    }
    if ($depth !== 0) {
        return null;
    }
    return substr($src, $start, $i - $start);
}

function hasSecurePermissionCallback(string $call): bool
{
    if (strpos($call, 'permission_callback') === false) {
        return false;
    }
    if (preg_match('/permission_callback\s*=>\s*([^,\)]+)/', $call, $m)) {
        $val = strtolower(trim($m[1], " \t\n\r\"'"));
        if ($val === '__return_true' || $val === 'true' || $val === '1') {
            return false;
        }
        return true;
    }
    return false;
}
