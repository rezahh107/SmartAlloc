<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$changelog = $root . '/CHANGELOG.md';
$changelogBody = '';
$version = '';
if (is_file($changelog)) {
    $content = (string)file_get_contents($changelog);
    $content = preg_replace('/^#.*\n+/', '', $content, 1);
    if (preg_match('/^##\s*([^\n]+)\n(.*?)(?=\n##\s|\z)/s', $content, $m)) {
        $version = trim($m[1]);
        $changelogBody = trim($m[2]);
    }
}

$metrics = [
    'coverage' => null,
    'rest' => null,
    'sql' => null,
    'license' => null,
    'secrets' => null,
    'manifest' => [],
];

$goNoGoFile = null;
$candidates = [
    $root . '/QA_REPORT/go-no-go.json',
    $root . '/QA_REPORT/GO-NO-GO.json',
    $root . '/artifacts/qa/go-no-go.json',
    $root . '/go-no-go.json',
];
foreach ($candidates as $c) {
    if (is_file($c)) {
        $goNoGoFile = $c;
        break;
    }
}

if ($goNoGoFile) {
    $data = json_decode((string)file_get_contents($goNoGoFile), true);
    if (is_array($data)) {
        $inputs = $data['inputs'] ?? [];
        $metrics['coverage'] = isset($inputs['qa']['coverage_percent']) ? (float)$inputs['qa']['coverage_percent'] : null;
        $metrics['rest'] = isset($inputs['rest']['count']) ? (int)$inputs['rest']['count'] : null;
        $metrics['sql'] = isset($inputs['sql']['count']) ? (int)$inputs['sql']['count'] : null;
        $metrics['license'] = isset($inputs['licenses']['denied']) ? (int)$inputs['licenses']['denied'] : null;
        $metrics['secrets'] = isset($inputs['secrets']['count']) ? (int)$inputs['secrets']['count'] : null;
        $m = $inputs['manifest'] ?? [];
        $entries = $m['entries'] ?? [];
        if (empty($entries) && isset($m['files']) && is_array($m['files'])) {
            $entries = $m['files'];
        }
        if (is_array($entries)) {
            $metrics['manifest'] = $entries;
        }
    }
}

$lines = [];
$lines[] = '<div dir="rtl">';
$lines[] = '# Release Notes' . ($version !== '' ? ' ' . $version : '');
$lines[] = '';
if ($changelogBody !== '') {
    $lines[] = $changelogBody;
    $lines[] = '';
}

$hasSignals = false;
foreach (['coverage','rest','sql','license','secrets'] as $k) {
    if ($metrics[$k] !== null) {
        $hasSignals = true;
        break;
    }
}

if ($hasSignals || !empty($metrics['manifest'])) {
    $lines[] = '## QA Signals';
    if ($metrics['coverage'] !== null) {
        $lines[] = '- Coverage: ' . $metrics['coverage'] . '%';
    }
    if ($metrics['rest'] !== null) {
        $lines[] = '- REST violations: ' . $metrics['rest'];
    }
    if ($metrics['sql'] !== null) {
        $lines[] = '- SQL violations: ' . $metrics['sql'];
    }
    if ($metrics['license'] !== null) {
        $lines[] = '- License denials: ' . $metrics['license'];
    }
    if ($metrics['secrets'] !== null) {
        $lines[] = '- Secrets found: ' . $metrics['secrets'];
    }
    $lines[] = '';
    if (!empty($metrics['manifest'])) {
        $lines[] = '### Manifest';
        $files = array_slice($metrics['manifest'], 0, 5);
        foreach ($files as $f) {
            $path = $f['path'] ?? '';
            $sha = $f['sha256'] ?? '';
            $lines[] = '- ' . $path . ': ' . substr($sha, 0, 12);
        }
        $total = count($metrics['manifest']);
        if ($total > count($files)) {
            $lines[] = '- ... (' . $total . ' files)';
        }
        $lines[] = '';
    }
}

$lines[] = '</div>';
$markdown = implode("\n", $lines) . "\n";

$dir = $root . '/artifacts/dist';
if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
}
file_put_contents($dir . '/release-notes.md', $markdown);

exit(0);
