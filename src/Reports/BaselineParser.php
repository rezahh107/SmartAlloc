<?php
// phpcs:ignoreFile
namespace SmartAlloc\Reports;
class BaselineParser
{
    public function parse(?string $yaml): ?array
    {
        if ($yaml === null || trim($yaml) === '') {
            return null;
        }
        $lines = preg_split('/\r?\n/', $yaml);
        $result = [];
        $stack = [&$result];
        $indentStack = [0];
        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }
            if (!preg_match('/^(\s*)([^:]+):(?:\s*(.*))?$/u', $line, $m)) {
                continue;
            }
            $indent = strlen($m[1]);
            $key = trim($m[2]);
            $value = isset($m[3]) ? trim($m[3], "'\"") : null;
            while ($indent < end($indentStack)) {
                array_pop($indentStack);
                array_pop($stack);
            }
            if ($value === null || $value === '') {
                $stack[count($stack)-1][$key] = [];
                $stack[] =& $stack[count($stack)-1][$key];
                $indentStack[] = $indent + 2;
            } else {
                $stack[count($stack)-1][$key] = $value;
            }
        }
        return $result ?: null;
    }
}
