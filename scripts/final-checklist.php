<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$items = [];

$doc = $root . '/docs/RELEASE_GATE.md';
if (is_file($doc)) {
    $content = (string)file_get_contents($doc);
    if (preg_match('/##\s*Checklist\s*\n(.*?)(?:\n##\s|\z)/s', $content, $m)) {
        $lines = preg_split('/\r?\n/', trim($m[1]));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '|') {
                continue;
            }
            $cols = array_map('trim', explode('|', trim($line, '|')));
            if (!isset($cols[0]) || $cols[0] === '' || stripos($cols[0], 'QA Plan') === 0 || $cols[0] === '---') {
                continue;
            }
            $items[$cols[0]] = 'pass';
        }
    }
}

$map = [
    'rest_violations' => 'REST guard',
    'sql_violations' => 'SQL prepare guard',
    'secrets_found' => 'Secrets scan',
    'license_denied' => 'License audit',
    'version_mismatch' => 'Version coherence & readme',
    'coverage_below_threshold' => 'Coverage (optional)',
];

$files = glob($root . '/QA_REPORT/*go-no-go*.json') ?: [];
foreach ($files as $file) {
    $data = json_decode((string)file_get_contents($file), true);
    if (!isset($data['summary']['reasons']) || !is_array($data['summary']['reasons'])) {
        continue;
    }
    foreach ($data['summary']['reasons'] as $reason) {
        $type = $reason['type'] ?? '';
        $severity = strtolower((string)($reason['severity'] ?? ''));
        if (!isset($map[$type])) {
            continue;
        }
        $items[$map[$type]] = ($severity === 'high') ? 'fail' : 'warn';
    }
}

$out = [];
foreach ($items as $name => $status) {
    $out[] = ['item' => $name, 'status' => $status];
}

$dir = $root . '/artifacts/qa';
if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
}
file_put_contents($dir . '/final-checklist.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

exit(0);
