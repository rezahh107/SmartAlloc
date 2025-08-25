<?php
declare(strict_types=1);

/**
 * Generate FEATURES.md from features.json with simple scoring:
 * +40 if all code_paths exist
 * +30 if all test_paths exist
 * +30 if security signals found in code (nonce/permission_callback/prepare)
 * Score caps at 100; badges: 🟢 90-100, 🟠 70-89, 🔴 <70
 */

$root = dirname(__DIR__);
$featuresFile = $root . '/features.json';
$outFile = $root . '/FEATURES.md';

$data = ['schema' => 1, 'features' => []];
if (is_file($featuresFile)) {
    $json = json_decode((string)file_get_contents($featuresFile), true);
    if (is_array($json) && isset($json['features']) && is_array($json['features'])) {
        $data = $json;
    }
}

function paths_exist(array $paths): bool {
    if (!$paths) return false;
    foreach ($paths as $p) {
        if (!file_exists($p)) return false;
    }
    return true;
}

function security_hit(array $paths): bool {
    $signals = ['wp_verify_nonce', 'permission_callback', 'DbSafe::mustPrepare', '$wpdb->prepare('];
    foreach ($paths as $p) {
        if (!is_file($p)) continue;
        $content = @file_get_contents($p);
        if ($content === false) continue;
        foreach ($signals as $sig) {
            if (strpos($content, $sig) !== false) return true;
        }
    }
    return false;
}

$rows = [];
foreach ($data['features'] as $f) {
    $name   = $f['name'] ?? 'Unnamed';
    $key    = $f['key'] ?? strtolower(preg_replace('/\s+/', '_', $name));
    $codes  = $f['code_paths'] ?? [];
    $tests  = $f['test_paths'] ?? [];

    $codeOk = paths_exist($codes);
    $testOk = paths_exist($tests);
    $secOk  = security_hit($codes);

    $score  = ($codeOk ? 40 : 0) + ($testOk ? 30 : 0) + ($secOk ? 30 : 0);
    if ($score > 100) $score = 100;

    $badge = $score >= 90 ? '🟢' : ($score >= 70 ? '🟠' : '🔴');

    $rows[] = [
        'name' => $name,
        'key'  => $key,
        'score'=> $score,
        'badge'=> $badge,
        'code' => $codeOk ? '✅' : '⚪',
        'test' => $testOk ? '✅' : '⚠️',
        'sec'  => $secOk  ? '✅' : '❌',
    ];
}

ob_start();
echo "# 📌 FEATURES\n\n";
echo "> Auto-generated. Do not edit manually. Source: features.json\n\n";
echo "| Feature | Key | Score | Impl | Tests | Security |\n";
echo "|---|---|---:|:---:|:---:|:---:|\n";
foreach ($rows as $r) {
    printf("| %s | `%s` | %s %d | %s | %s | %s |\n",
        $r['name'], $r['key'], $r['badge'], $r['score'], $r['code'], $r['test'], $r['sec']
    );
}
echo "\n**Legend:** 🟢 90–100 (Ready), 🟠 70–89 (Testing), 🔴 <70 (In progress)\n";
file_put_contents($outFile, ob_get_clean());
echo "Generated FEATURES.md\n";

