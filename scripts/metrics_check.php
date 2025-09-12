<?php
@mkdir(__DIR__ . '/../build', 0777, true);
$out = [
    'queue_depth' => 0,
    'dlq' => 0,
];
file_put_contents(__DIR__ . '/../build/metrics.json', json_encode($out, JSON_PRETTY_PRINT));
fwrite(STDOUT, "metrics_check: wrote build/metrics.json\n");
