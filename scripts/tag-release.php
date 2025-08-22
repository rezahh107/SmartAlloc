<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$options = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--')) {
        [$k, $v] = array_pad(explode('=', $arg, 2), 2, null);
        $options[$k] = $v ?? true;
    }
}
$tag = $options['--tag'] ?? ($argv[1] ?? null);
if (!$tag) {
    fwrite(STDERR, "--tag required\n");
    exit(1);
}
$profile = $options['--profile'] ?? 'ga';
$enforce = isset($options['--enforce']) ? filter_var($options['--enforce'], FILTER_VALIDATE_BOOLEAN) : false;

// preflight summary
@mkdir($root . '/artifacts/release', 0777, true);
exec(sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($root . '/scripts/tag-preflight.php') . ($enforce ? ' --enforce' : '')), $_, $rc);
if ($rc !== 0) {
    echo "WARN: preflight failed\n";
}

// GA enforcer
$cmd = [escapeshellarg(PHP_BINARY), escapeshellarg($root . '/scripts/ga-enforcer.php'), '--profile=' . $profile, '--junit'];
if ($enforce) {
    $cmd[] = '--enforce';
} else {
    echo "GA Enforcer: advisory\n";
}
exec(implode(' ', $cmd), $out, $gaRc);
if ($gaRc !== 0 && $enforce) {
    echo "WARN: GA Enforcer failed\n";
}

// dist build + manifest + sbom + readme
exec(sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($root . '/scripts/dist-build.php')), $_, $rc);
if ($rc !== 0) { exit($rc); }
exec(sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($root . '/scripts/dist-manifest.php')), $_, $rc);
if ($rc !== 0) { exit($rc); }
exec(sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($root . '/scripts/sbom-from-composer.php')), $_, $rc);
if ($rc !== 0) { exit($rc); }
exec(sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($root . '/scripts/validate-readme.php')), $_, $rc);
if ($rc !== 0 && $enforce) { echo "WARN: readme validation failed\n"; }

// release notes
exec(sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($root . '/scripts/release-notes.php')), $_, $rc);
if ($rc !== 0) { exit($rc); }

$releaseDir = $root . '/artifacts/release/' . $tag;
@mkdir($releaseDir, 0777, true);
$distArtifacts = $root . '/artifacts/dist';
foreach (['manifest.json','sbom.json','readme-lint.json','release-notes.md'] as $file) {
    $src = $distArtifacts . '/' . $file;
    if (is_file($src)) {
        copy($src, $releaseDir . '/' . $file);
    }
}

// sha256 sums
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($releaseDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $info) {
    if ($info->isFile()) {
        $rel = substr($info->getPathname(), strlen($releaseDir) + 1);
        if ($rel === 'SHA256SUMS.txt' || $rel === 'SHA256SUMS.sig') {
            continue;
        }
        $files[$rel] = hash_file('sha256', $info->getPathname());
    }
}
ksort($files);
$sumLines = [];
foreach ($files as $name => $hash) {
    $sumLines[] = $hash . '  ' . $name;
}
file_put_contents($releaseDir . '/SHA256SUMS.txt', implode("\n", $sumLines) . "\n");

// sign if possible
$gpg = trim(shell_exec('command -v gpg')); 
if ($gpg !== '') {
    $cmd = sprintf('gpg --output %s --detach-sign %s', escapeshellarg($releaseDir . '/SHA256SUMS.sig'), escapeshellarg($releaseDir . '/SHA256SUMS.txt'));
    exec($cmd, $_, $sigRc);
    if ($sigRc !== 0) {
        echo "WARN: gpg sign failed\n";
    }
} else {
    echo "WARN: gpg not available\n";
}

// GA readiness warnings
$gaReady = $root . '/artifacts/ga/GA_READY.txt';
if ($enforce && is_file($gaReady)) {
    $txt = file_get_contents($gaReady);
    if ($txt !== false && preg_match('/^WARN /m', $txt)) {
        fwrite(STDERR, "GA readiness warnings present\n");
        exit(1);
    }
}

// git tag
exec(sprintf('git tag %s', escapeshellarg($tag)), $_, $tagRc);
if ($tagRc !== 0) {
    // ignore if exists
}
$tagJson = [
    'tag' => $tag,
    'commit' => trim(shell_exec('git rev-parse HEAD')), 
    'generated_at_utc' => gmdate('c'),
];
file_put_contents($root . '/artifacts/release/tag.json', json_encode($tagJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

exit(0);
