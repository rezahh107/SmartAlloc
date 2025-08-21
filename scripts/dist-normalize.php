<?php
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

// Deterministic zip normalizer.
// Usage: php scripts/dist-normalize.php <folder> [--outfile=path]

$root = dirname(__DIR__);
$input = $argv[1] ?? '';
$outOpt = $argv[2] ?? '';
$options = [];
if (str_starts_with($outOpt, '--outfile=')) {
    $options['outfile'] = substr($outOpt, 10);
}

$dir = $input !== '' ? realpath($input) : null;
if ($dir === false || $dir === null || !is_dir($dir)) {
    echo json_encode(['error' => 'invalid_input']) . PHP_EOL;
    exit(0);
}

$base = basename($dir);
$outFile = $options['outfile'] ?? ($root . '/artifacts/dist/' . $base . '-normalized.zip');
$distDir = dirname($outFile);
if (!is_dir($distDir)) {
    mkdir($distDir, 0777, true);
}

$mtime = gmmktime(0, 0, 0, 1, 1, 2020);
$items = [];
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iter as $info) {
    $rel = substr($info->getPathname(), strlen($dir) + 1);
    $rel = str_replace('\\', '/', $rel);
    $items[] = ['path' => $rel, 'dir' => $info->isDir()];
}

usort($items, static function ($a, $b) {
    return strcmp($a['path'], $b['path']);
});

$zip = new ZipArchive();
$zip->open($outFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$fileCount = 0;
foreach ($items as $item) {
    $full = $dir . '/' . $item['path'];
    if ($item['dir']) {
        $zip->addEmptyDir($item['path']);
        if (method_exists($zip, 'setMtimeName')) {
            $zip->setMtimeName($item['path'], $mtime);
        }
        if (method_exists($zip, 'setExternalAttributesName')) {
            $zip->setExternalAttributesName($item['path'], ZipArchive::OPSYS_UNIX, (040755 << 16));
        }
        continue;
    }
    $data = (string)file_get_contents($full);
    if (isText($full)) {
        $data = preg_replace("/\r\n?/,\n", $data);
    }
    $zip->addFromString($item['path'], $data);
    if (method_exists($zip, 'setMtimeName')) {
        $zip->setMtimeName($item['path'], $mtime);
    }
    if (method_exists($zip, 'setExternalAttributesName')) {
        $zip->setExternalAttributesName($item['path'], ZipArchive::OPSYS_UNIX, (0100644 << 16));
    }
    $fileCount++;
}
$zip->close();

$summary = [
    'files' => $fileCount,
    'size' => is_file($outFile) ? filesize($outFile) : 0,
    'sha256' => is_file($outFile) ? hash_file('sha256', $outFile) : null,
];

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

function isText(string $path): bool {
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return false;
    }
    $chunk = fread($fh, 512);
    fclose($fh);
    return $chunk !== false && strpos($chunk, "\0") === false;
}
