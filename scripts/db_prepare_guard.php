<?php
// Scan for $wpdb->query / get_results / get_var with interpolated variables (very simple heuristic)
$root = __DIR__ . '/../';
$paths = ['src', 'tests'];
$hits = [];
foreach ($paths as $p) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.$p, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $code = file_get_contents($f->getPathname());
        // crude patterns: query("...$var..."), get_results("...$var...")
        $patterns = [
            '/\\$wpdb->query\(\s*"(?:[^"\\\\]|\\\\.)*\\$[a-zA-Z_][a-zA-Z0-9_]*(?:[^"\\\\]|\\\\.)*"\s*\)/',
            '/\\$wpdb->get_results\(\s*"(?:[^"\\\\]|\\\\.)*\\$[a-zA-Z_][a-zA-Z0-9_]*(?:[^"\\\\]|\\\\.)*"\s*\)/',
            '/\\$wpdb->get_row\(\s*"(?:[^"\\\\]|\\\\.)*\\$[a-zA-Z_][a-zA-Z0-9_]*(?:[^"\\\\]|\\\\.)*"\s*\)/',
            '/\\$wpdb->get_var\(\s*"(?:[^"\\\\]|\\\\.)*\\$[a-zA-Z_][a-zA-Z0-9_]*(?:[^"\\\\]|\\\\.)*"\s*\)/'
        ];
        foreach ($patterns as $rx) {
            if (preg_match($rx, $code)) {
                $hits[] = $f->getPathname();
                break;
            }
        }
    }
}
if (!is_dir('build')) mkdir('build', 0777, true);
file_put_contents('build/db_prepare_guard.json', json_encode(['unsafe_sql_files' => array_values(array_unique($hits))], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "DB Prepare Guard: scanned, found ".count($hits)." file(s).\n";
