<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$keep = isset($argv[1]) ? (int)$argv[1] : 3;
$readme = $root . '/readme.txt';
$outDir = $root . '/artifacts/wporg';
@mkdir($outDir, 0777, true);
$result = ['kept' => 0, 'removed' => 0, 'versions' => []];

if (!is_file($readme)) {
    $result['status'] = 'readme_missing';
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$content = (string)file_get_contents($readme);
$lines = preg_split('/\r?\n/', $content);
$idx = null;
for ($i = 0; $i < count($lines); $i++) {
    if (trim($lines[$i]) === '== Changelog ==') {
        $idx = $i;
        break;
    }
}
if ($idx === null) {
    file_put_contents($outDir . '/readme-truncated.txt', $content);
    $result['status'] = 'changelog_missing';
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}
$header = array_slice($lines, 0, $idx + 1);
$body = array_slice($lines, $idx + 1);
$sections = [];
$current = null;
$currentLines = [];
foreach ($body as $line) {
    if (preg_match('/^=\s*([^=]+?)\s*=/', $line, $m)) {
        if ($current !== null) {
            $sections[] = ['version' => $current, 'lines' => $currentLines];
        }
        $current = trim($m[1]);
        $currentLines = [];
        continue;
    }
    if ($current !== null) {
        $currentLines[] = $line;
    }
}
if ($current !== null) {
    $sections[] = ['version' => $current, 'lines' => $currentLines];
}

$kept = array_slice($sections, 0, $keep);
$result['kept'] = count($kept);
$result['removed'] = count($sections) - $result['kept'];
$output = $header;
foreach ($kept as $section) {
    $linesCount = count($section['lines']);
    $truncated = false;
    if ($linesCount > 200) {
        $section['lines'] = array_slice($section['lines'], 0, 200);
        $truncated = true;
    }
    $result['versions'][$section['version']] = ['lines' => $linesCount, 'truncated' => $truncated];
    $output[] = '= ' . $section['version'] . ' =';
    $output = array_merge($output, $section['lines']);
}
$fileOut = implode("\n", $output);
file_put_contents($outDir . '/readme-truncated.txt', $fileOut);

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);
