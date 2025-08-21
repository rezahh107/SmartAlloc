<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$distDir = $root . '/artifacts/dist';
if (!is_dir($distDir)) {
    @mkdir($distDir, 0777, true);
}
$outFile = $distDir . '/release-draft.md';
$lines = [];
$lines[] = '<div dir="rtl">';
$lines[] = '# Release Draft';
$lines[] = '';
$releaseNotesPath = $distDir . '/release-notes.md';
$releaseNotes = is_file($releaseNotesPath) ? (string)file_get_contents($releaseNotesPath) : '';

// Highlights from CHANGELOG
$highlights = [];
$changelog = $root . '/CHANGELOG.md';
if (is_file($changelog)) {
    $content = (string)file_get_contents($changelog);
    if (preg_match('/^##\s*\[?([^\]\s]+)\]?\s*-\s*(\d{4}-\d{2}-\d{2})\s*\n(.*?)(?:\n##\s|\z)/s', $content, $m)) {
        $body = trim($m[3]);
        foreach (preg_split('/\r?\n/', $body) as $line) {
            if (preg_match('/^\s*[-*]\s*(.+)/', $line, $lm)) {
                $highlights[] = trim($lm[1]);
            }
        }
    }
}
$lines[] = '## Highlights';
if ($highlights) {
    foreach ($highlights as $h) {
        $lines[] = '- ' . $h;
    }
} else {
    $lines[] = '_n/a_';
}
$lines[] = '';

// QA Summary from go-no-go
$qaPath = $root . '/artifacts/qa/go-no-go.json';
$qaSummary = [];
$reasons = [];
if (is_file($qaPath)) {
    $qa = json_decode((string)file_get_contents($qaPath), true);
    if (is_array($qa)) {
        $inputs = $qa['inputs'] ?? [];
        $qaSummary['coverage'] = $inputs['qa']['coverage_percent'] ?? null;
        $qaSummary['rest'] = $inputs['rest']['count'] ?? null;
        $qaSummary['sql'] = $inputs['sql']['count'] ?? null;
        $qaSummary['licenses'] = $inputs['licenses']['denied'] ?? null;
        $qaSummary['secrets'] = $inputs['secrets']['count'] ?? null;
        $qaSummary['verdict'] = $qa['summary']['go'] ?? null;
        $reasons = $qa['summary']['reasons'] ?? [];
    }
}
$lines[] = '## QA Summary';
if ($qaSummary) {
    $lines[] = '- Coverage: ' . ($qaSummary['coverage'] !== null ? $qaSummary['coverage'] . '%' : 'n/a');
    $lines[] = '- REST violations: ' . ($qaSummary['rest'] ?? 'n/a');
    $lines[] = '- SQL violations: ' . ($qaSummary['sql'] ?? 'n/a');
    $lines[] = '- License denials: ' . ($qaSummary['licenses'] ?? 'n/a');
    $lines[] = '- Secrets found: ' . ($qaSummary['secrets'] ?? 'n/a');
    if ($qaSummary['verdict'] !== null) {
        $lines[] = '- Verdict: ' . ($qaSummary['verdict'] ? 'GO' : 'NO-GO');
    }
} else {
    $lines[] = '_n/a_';
}
$lines[] = '';

// Artifacts from manifest and SBOM
$lines[] = '## Artifacts';
$manifestPath = $distDir . '/manifest.json';
if (is_file($manifestPath)) {
    $manifest = json_decode((string)file_get_contents($manifestPath), true);
    $entries = $manifest['entries'] ?? [];
    if (empty($entries) && isset($manifest['files']) && is_array($manifest['files'])) {
        $entries = $manifest['files'];
    }
    if (is_array($entries)) {
        foreach ($entries as $file) {
            $path = $file['path'] ?? '';
            $sha = $file['sha256'] ?? '';
            if ($path !== '' && $sha !== '') {
                $lines[] = '- `' . $path . '` `' . $sha . '`';
            }
        }
    }
} else {
    $lines[] = '_n/a_';
}
$sbomPath = $distDir . '/sbom.json';
if (is_file($sbomPath)) {
    $sbom = json_decode((string)file_get_contents($sbomPath), true);
    $count = is_array($sbom['components'] ?? null) ? count($sbom['components']) : 0;
    $lines[] = ''; 
    $lines[] = 'SBOM components: ' . $count;
}
$lines[] = '';

// Upgrade & Compatibility matrix
$lines[] = '## Upgrade & Compatibility';
$pluginFile = $root . '/smart-alloc.php';
$wpMin = $wpTested = $phpReq = $gfReq = 'n/a';
if (is_file($pluginFile)) {
    $contents = (string)file_get_contents($pluginFile);
    if (preg_match('/Requires at least:\s*(.+)/i', $contents, $m)) {
        $wpMin = trim($m[1]);
    }
    if (preg_match('/Tested up to:\s*(.+)/i', $contents, $m)) {
        $wpTested = trim($m[1]);
    }
    if (preg_match('/Requires PHP:\s*(.+)/i', $contents, $m)) {
        $phpReq = trim($m[1]);
    }
}
// Attempt to detect Gravity Forms version from docs or readme
$readme = $root . '/README.md';
if (is_file($readme)) {
    $rd = (string)file_get_contents($readme);
    if (preg_match('/Gravity Forms[^\n\r]*?([0-9][0-9\.\+]*)/i', $rd, $m)) {
        $gfReq = $m[1];
    }
}
$lines[] = '| WordPress | Gravity Forms | PHP |';
$lines[] = '| --- | --- | --- |';
$lines[] = '| ' . $wpMin . '-' . $wpTested . ' | ' . $gfReq . ' | ' . $phpReq . ' |';
$lines[] = '';

// Known Issues / Skips
$lines[] = '## Known Issues / Skips';
if ($reasons) {
    foreach ($reasons as $r) {
        $type = $r['type'] ?? 'unknown';
        $sev = $r['severity'] ?? 'info';
        $lines[] = '- ' . $type . ' (' . $sev . ')';
    }
} elseif ($releaseNotes !== '' && preg_match('/##\s*Known Issues\s*\n(.*?)(?:\n##|\z)/s', $releaseNotes, $m)) {
    foreach (preg_split('/\r?\n/', trim($m[1])) as $line) {
        if ($line !== '') {
            $lines[] = $line;
        }
    }
} else {
    $lines[] = '_n/a_';
}
$lines[] = '';
$lines[] = '</div>';

file_put_contents($outFile, implode("\n", $lines) . "\n");
exit(0);
