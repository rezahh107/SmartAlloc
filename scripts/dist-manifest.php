<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$argPath = $argv[1] ?? '';
$path = resolvePath($argPath, $root);
$files = [];

if ($path !== null) {
    if (is_file($path) && preg_match('/\.zip$/i', $path)) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $content = $zip->getFromIndex($i);
                $files[] = [
                    'path' => $name,
                    'sha256' => hash('sha256', (string)$content),
                    'size' => $stat['size'] ?? strlen((string)$content),
                ];
            }
            $zip->close();
        }
    } elseif (is_dir($path)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iter as $fileinfo) {
            if ($fileinfo->isFile()) {
                $rel = substr($fileinfo->getPathname(), strlen($path) + 1);
                $files[] = [
                    'path' => str_replace('\\', '/', $rel),
                    'sha256' => hash_file('sha256', $fileinfo->getPathname()),
                    'size' => $fileinfo->getSize(),
                ];
            }
        }
    }
}

$manifestDir = $root . '/artifacts/dist';
if (!is_dir($manifestDir)) {
    mkdir($manifestDir, 0777, true);
}
file_put_contents($manifestDir . '/manifest.json', json_encode(['files' => $files], JSON_PRETTY_PRINT));

exit(0);

function resolvePath(string $arg, string $root): ?string {
    if ($arg !== '') {
        $real = realpath($arg);
        return $real !== false ? $real : null;
    }
    $dist = $root . '/dist';
    if (is_dir($dist) || is_file($dist)) {
        return $dist;
    }
    $zips = glob($root . '/build/*.zip');
    if (!empty($zips)) {
        return $zips[0];
    }
    return null;
}
