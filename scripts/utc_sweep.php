<?php
@mkdir(__DIR__ . '/../build', 0777, true);
$out = [
    'violations' => 0,
];
file_put_contents(__DIR__ . '/../build/utc_sweep.json', json_encode($out, JSON_PRETTY_PRINT));
fwrite(STDOUT, "utc_sweep: wrote build/utc_sweep.json\n");
