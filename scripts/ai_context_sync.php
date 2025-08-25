#!/usr/bin/env php
<?php
$context = ['decisions' => []];
file_put_contents(__DIR__ . '/../ai_context.json', json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
