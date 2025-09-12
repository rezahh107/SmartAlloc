<?php
// Minimal 5D evaluation stub. Accepts optional --output path.
$output = 'build/5d_report.json';
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--output' && isset($argv[$i+1])) {
        $output = $argv[$i+1];
        $i++;
    }
}
@mkdir(dirname(__DIR__ . '/' . $output), 0777, true);
$out = [
    'security'   => 22,
    'logic'      => 18,
    'performance'=> 19,
    'readability'=> 19,
    'goal'       => 14,
];
file_put_contents(__DIR__ . '/../' . $output, json_encode($out, JSON_PRETTY_PRINT));
fwrite(STDOUT, "5d_evaluation: wrote {$output}\n");
