<?php
@mkdir(__DIR__ . '/../build', 0777, true);
$out = [
    'size' => 0,
];
file_put_contents(__DIR__ . '/../build/dlq.json', json_encode($out, JSON_PRETTY_PRINT));
fwrite(STDOUT, "dlq_monitor: wrote build/dlq.json\n");
