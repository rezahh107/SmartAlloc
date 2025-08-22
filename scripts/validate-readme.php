<?php
declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__);
$file = $root . '/readme.txt';
$warnings = [];

if (!is_file($file)) {
    $warnings[] = 'missing_readme';
    $content = '';
} else {
    $content = (string)file_get_contents($file);
    if (!preg_match('/^Requires at least:\s*\S+/mi', $content)) {
        $warnings[] = 'requires_at_least';
    }
    if (!preg_match('/^Tested up to:\s*\S+/mi', $content)) {
        $warnings[] = 'tested_up_to';
    }
    if (!preg_match('/^Stable tag:\s*\S+/mi', $content)) {
        $warnings[] = 'stable_tag';
    }
    if (preg_match('/==\s*Screenshots\s*==(?P<section>.*?)(\n==|\z)/si', $content, $m)) {
        $lines = array_filter(array_map('trim', preg_split('/\r?\n/', trim($m['section']))));
        $n = 1;
        foreach ($lines as $line) {
            if (!preg_match('/^' . $n . '\.\s+/', $line)) {
                $warnings[] = 'screenshots_format';
                break;
            }
            $n++;
        }
    }
}

$outDir = $root . '/artifacts/dist';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$result = ['warnings' => $warnings];
file_put_contents($outDir . '/readme-lint.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit(0);
