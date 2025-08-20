<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$argPath = $argv[1] ?? '';
$path = resolvePath($argPath, $root);
$violations = [];
$summary = ['scanned' => 0];

if ($path === null) {
    echo json_encode(['summary' => ['scanned' => 0, 'error' => 'path not found'], 'violations' => []], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

[$workDir, $cleanup] = prepareWorkdir($path);

// Adjust if archive contains single top-level directory
$entries = array_values(array_diff(scandir($workDir), ['.', '..']));
if (count($entries) === 1 && is_dir($workDir . DIRECTORY_SEPARATOR . $entries[0])) {
    $workDir = $workDir . DIRECTORY_SEPARATOR . $entries[0];
}

$pluginFile = null;

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($workDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $fileinfo) {
    $rel = substr($fileinfo->getPathname(), strlen($workDir) + 1);
    $relNorm = str_replace('\\', '/', $rel);
    $summary['scanned']++;

    // Detect plugin main file
    if ($pluginFile === null && $fileinfo->isFile() && $fileinfo->getExtension() === 'php' && strpos($relNorm, '/') === false) {
        $chunk = (string)file_get_contents($fileinfo->getPathname(), false, null, 0, 8192);
        if (preg_match('/Plugin Name\s*:/i', $chunk)) {
            $pluginFile = $fileinfo->getPathname();
        }
    }

    // dev files
    $devPatterns = [
        '#(^|/)\.git#',
        '#(^|/)\.github/#i',
        '#(^|/)node_modules/#i',
        '#(^|/)vendor/bin/#i',
        '#(^|/)tests/#i',
        '#\.map$#i',
        '#\.env$#i',
        '#\.editorconfig$#i',
        '#\.phpcs#i',
        '#\.psalm#i',
        '#\.phpunit#i',
        '#\.md$#i',
        '#\.DS_Store$#',
    ];
    foreach ($devPatterns as $p) {
        if (preg_match($p, $relNorm)) {
            if (!preg_match('#^readme\.txt$#i', $relNorm)) {
                $violations[] = ['type' => 'dev_file', 'file' => $relNorm];
            }
            break;
        }
    }

    // conflict markers
    if ($fileinfo->isFile()) {
        $contents = @file_get_contents($fileinfo->getPathname());
        $markers = [str_repeat('<', 7), str_repeat('=', 7), str_repeat('>', 7)];
        $pattern = '/' . implode('|', array_map('preg_quote', $markers)) . '/';
        if ($contents !== false && preg_match($pattern, $contents, $m, PREG_OFFSET_CAPTURE)) {
            $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
            $violations[] = ['type' => 'conflict_marker', 'file' => $relNorm, 'line' => $line];
        }
        // asset size
        $ext = strtolower($fileinfo->getExtension());
        $size = $fileinfo->getSize();
        $imgExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];
        if (in_array($ext, $imgExt, true) && $size > 524288) {
            $violations[] = ['type' => 'asset_size', 'file' => $relNorm, 'note' => (string)$size];
        }
        if ($ext === 'svg' && strpos($relNorm, '/sanitized/') === false) {
            $violations[] = ['type' => 'svg_unallowed', 'file' => $relNorm];
        }
    }

    // permissions
    $perm = substr(sprintf('%o', $fileinfo->getPerms()), -4);
    if ($fileinfo->isDir() && $perm !== '0755') {
        $violations[] = ['type' => 'permissions', 'file' => $relNorm, 'note' => $perm];
    } elseif ($fileinfo->isFile() && $perm !== '0644') {
        $violations[] = ['type' => 'permissions', 'file' => $relNorm, 'note' => $perm];
    }
}

// plugin headers
if ($pluginFile !== null) {
    $pluginRel = substr($pluginFile, strlen($workDir) + 1);
    $header = (string)file_get_contents($pluginFile, false, null, 0, 8192);
    $required = ['Plugin Name', 'Version', 'Requires at least', 'Tested up to', 'Requires PHP'];
    foreach ($required as $req) {
        if (!preg_match('/' . preg_quote($req, '/') . '\s*:/i', $header)) {
            $violations[] = ['type' => 'header_missing', 'file' => $pluginRel, 'note' => $req];
        }
    }
    if (preg_match('/Text Domain\s*:\s*(\S+)/i', $header, $m)) {
        $textDomain = trim($m[1]);
        $code = (string)file_get_contents($pluginFile);
        if (preg_match('/load_plugin_textdomain\(\s*["\']([^"\']+)["\']\s*,\s*false\s*,\s*([^\)]+)\)/', $code, $m2)) {
            if ($m2[1] !== $textDomain) {
                $violations[] = ['type' => 'text_domain_mismatch', 'file' => $pluginRel];
            }
            if (stripos($m2[2], 'languages') === false) {
                $violations[] = ['type' => 'text_domain_path', 'file' => $pluginRel];
            }
        } else {
            $violations[] = ['type' => 'text_domain_load_missing', 'file' => $pluginRel];
        }
    } else {
        $violations[] = ['type' => 'header_missing', 'file' => $pluginRel, 'note' => 'Text Domain'];
    }
} else {
    $violations[] = ['type' => 'plugin_file_missing'];
}

// readme.txt
$readme = $workDir . '/readme.txt';
if (!is_file($readme)) {
    $violations[] = ['type' => 'missing_readme', 'file' => 'readme.txt'];
} else {
    $readmeContent = (string)file_get_contents($readme);
    if (stripos($readmeContent, '== Changelog ==') === false || stripos($readmeContent, '== Description ==') === false) {
        $violations[] = ['type' => 'readme_sanity', 'file' => 'readme.txt'];
    }
}

$summary['violations'] = count($violations);
$result = ['summary' => $summary, 'violations' => $violations];

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

if ($cleanup !== null) {
    cleanupDir($cleanup);
}
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

function prepareWorkdir(string $path): array {
    if (is_file($path) && preg_match('/\.zip$/i', $path)) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $tmp = sys_get_temp_dir() . '/dist_audit_' . uniqid();
            mkdir($tmp);
            $zip->extractTo($tmp);
            $zip->close();
            return [$tmp, $tmp];
        }
    }
    return [$path, null];
}

function cleanupDir(string $dir): void {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($dir);
}
