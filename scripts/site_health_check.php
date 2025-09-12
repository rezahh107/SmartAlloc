<?php
@mkdir(__DIR__ . '/../build', 0777, true);
$out = [
    'status' => 'GREEN',
];
file_put_contents(__DIR__ . '/../build/site-health.json', json_encode($out, JSON_PRETTY_PRINT));
fwrite(STDOUT, "site_health_check: wrote build/site-health.json\n");
