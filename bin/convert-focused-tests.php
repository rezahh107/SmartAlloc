<?php
declare(strict_types=1);

$xmlFile = __DIR__ . '/../focused-tests.xml';
$jsonFile = __DIR__ . '/../focused-tests.json';

if (!file_exists($xmlFile)) {
    fwrite(STDERR, "focused-tests.xml not found\n");
    exit(1);
}

$xml = simplexml_load_file($xmlFile);
if ($xml === false) {
    fwrite(STDERR, "Failed to parse XML\n");
    exit(1);
}

$data = json_decode(json_encode($xml), true);
file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Wrote focused-tests.json\n";
