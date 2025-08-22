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
    'i18n' => [],
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

$i18nLint = $root . '/artifacts/i18n-lint.json';
if (is_file($i18nLint)) {
    $data = json_decode((string)file_get_contents($i18nLint), true);
    if (is_array($data)) {
        $metrics['i18n']['wrong_domain'] = isset($data['wrong_domain']) ? count((array)$data['wrong_domain']) : 0;
        $metrics['i18n']['placeholder_mismatch'] = isset($data['placeholder_mismatch']) ? count((array)$data['placeholder_mismatch']) : 0;
    }
}
$potRefresh = $root . '/artifacts/i18n/pot-refresh.json';
if (is_file($potRefresh)) {
    $data = json_decode((string)file_get_contents($potRefresh), true);
    if (is_array($data)) {
        $metrics['i18n']['pot_entries'] = $data['pot_entries'] ?? 0;
        $metrics['i18n']['domain_mismatch'] = $data['domain_mismatch'] ?? 0;
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
if (!$hasSignals && !empty($metrics['i18n'])) {
    $hasSignals = true;
}

if ($hasSignals || !empty($metrics['manifest']) || !empty($metrics['i18n'])) {
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
    if (!empty($metrics['i18n'])) {
        $lines[] = '### I18N';
        foreach ($metrics['i18n'] as $k => $v) {
            $lines[] = '- ' . $k . ': ' . $v;
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
