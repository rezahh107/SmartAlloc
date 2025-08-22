<?php

declare(strict_types=1);

// Normalize GA artifacts for RC profile expectations.
$artifacts = __DIR__ . '/../artifacts';
foreach (['metrics', 'logs', 'trace'] as $type) {
    $file = $artifacts . '/' . $type . '.json';
    if (!is_file($file)) {
        continue;
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        continue;
    }
    foreach ($data as &$row) {
        if (isset($row['email'])) {
            $row['email'] = '***';
        }
        if (isset($row['mobile'])) {
            $row['mobile'] = '***';
        }
    }
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}
