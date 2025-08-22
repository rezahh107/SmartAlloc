<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$changelogFile = $root . '/CHANGELOG.md';
$fromTag = trim(shell_exec('git describe --tags --abbrev=0 2>/dev/null'));
$range = $fromTag !== '' ? $fromTag . '..HEAD' : 'HEAD';
$log = trim(shell_exec('git log --no-merges --format=%s ' . escapeshellarg($range)));
$lines = array_filter(array_map('trim', explode("\n", $log)));
$sections = [
    'Added' => [],
    'Changed' => [],
    'Fixed' => [],
    'Docs' => [],
    'Other' => [],
];
foreach ($lines as $line) {
    if (preg_match('/^(\w+)(?:\([^)]*\))?:\s*(.+)$/', $line, $m)) {
        $type = $m[1];
        $msg = $m[2];
        switch ($type) {
            case 'feat':
                $sections['Added'][] = $msg;
                break;
            case 'fix':
                $sections['Fixed'][] = $msg;
                break;
            case 'perf':
            case 'refactor':
                $sections['Changed'][] = $msg;
                break;
            case 'docs':
                $sections['Docs'][] = $msg;
                break;
            default:
                $sections['Other'][] = $msg;
        }
    } else {
        $sections['Other'][] = $line;
    }
}
$content = [];
foreach ($sections as $head => $items) {
    if ($items === []) {
        continue;
    }
    $content[] = '### ' . $head;
    foreach ($items as $it) {
        $content[] = '- ' . $it;
    }
    $content[] = '';
}
$body = implode("\n", $content);
if ($body === '') {
    $body = "- Minor changes"; // fallback
}
if (!is_file($changelogFile)) {
    file_put_contents($changelogFile, "# Changelog\n\n## [Unreleased]\n\n" . $body . "\n");
    exit(0);
}
$changelog = file_get_contents($changelogFile);
$pattern = '/## \[Unreleased\]\n(?P<body>.*?)(?=\n## |\z)/s';
if (preg_match($pattern, $changelog, $m)) {
    $replacement = "## [Unreleased]\n" . $body . "\n";
    $changelog = preg_replace($pattern, $replacement, $changelog, 1);
    file_put_contents($changelogFile, rtrim($changelog) . "\n");
}
exit(0);
