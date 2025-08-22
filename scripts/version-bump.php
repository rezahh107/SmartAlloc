<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$version = $argv[1] ?? null;
if ($version === null) {
    fwrite(STDERR, "Usage: php scripts/version-bump.php <version>\n");
    exit(1);
}
$rc = str_contains($version, '-rc.');
$date = gmdate('Y-m-d');

// smart-alloc.php
$pluginFile = $root . '/smart-alloc.php';
if (is_file($pluginFile)) {
    $plugin = file_get_contents($pluginFile);
    if ($plugin !== false) {
        $plugin = preg_replace('/^Version:\s*.+$/mi', 'Version: ' . $version, $plugin, 1);
        file_put_contents($pluginFile, $plugin);
    }
}

// readme.txt
$readmeFile = $root . '/readme.txt';
if (is_file($readmeFile)) {
    $readme = file_get_contents($readmeFile);
    if ($readme !== false) {
        if (!$rc) {
            $readme = preg_replace('/^Stable tag:\s*.+$/mi', 'Stable tag: ' . $version, $readme, 1);
        }
        file_put_contents($readmeFile, $readme);
    }
}

// CHANGELOG.md
$changelogFile = $root . '/CHANGELOG.md';
if (!is_file($changelogFile)) {
    file_put_contents($changelogFile, "# Changelog\n\n## [Unreleased]\n\n");
}
$changelog = file_get_contents($changelogFile);
if ($changelog === false) {
    $changelog = "# Changelog\n";
}
if (!preg_match('/## \[Unreleased\]\n/s', $changelog)) {
    $changelog = "# Changelog\n\n## [Unreleased]\n\n" . trim(preg_replace('/^#.*\n+/', '', $changelog));
}
$pattern = '/## \[Unreleased\]\n(?P<body>.*?)(?=\n## |\z)/s';
if (preg_match($pattern, $changelog, $m)) {
    $body = rtrim($m['body']);
    $releaseHeader = '## [v' . $version . '] - ' . $date . "\n";
    $replacement = "## [Unreleased]\n\n" . $releaseHeader;
    if ($body !== '') {
        $replacement .= $body . "\n";
    }
    $changelog = preg_replace($pattern, $replacement, $changelog, 1);
    file_put_contents($changelogFile, rtrim($changelog) . "\n");
}
exit(0);
