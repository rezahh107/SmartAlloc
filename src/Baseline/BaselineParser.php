<?php
// src/Baseline/BaselineParser.php

declare(strict_types=1);

namespace SmartAlloc\Baseline;

class BaselineParser
{
    public function parse(?string $yaml): ?array
    {
        if (null === $yaml || '' === trim($yaml)) {
            return null;
        }
        if (function_exists('yaml_parse')) {
            $parsed = yaml_parse($yaml);
            return is_array($parsed) ? $parsed : null;
        }
        $lines   = preg_split('/\r?\n/', trim($yaml));
        $result  = [];
        $stack   = [&$result];
        $indents = [0];
        foreach ($lines as $line) {
            if ('' === trim($line)) {
                continue;
            }
            if (!preg_match('/^(\s*)([^:]+):\s*(.*)$/', $line, $m)) {
                continue;
            }
            $indentStr = $m[1];
            $key       = $m[2];
            $value     = $m[3];
            $indent    = strlen($indentStr);
            while ($indent < end($indents)) {
                array_pop($indents);
                array_pop($stack);
            }
            if ($value === '') {
                $stack[count($stack)-1][$key] = [];
                $stack[]                       =& $stack[count($stack)-1][$key];
                $indents[]                     = $indent + 2;
            } else {
                $stack[count($stack)-1][$key] = trim($value, "'\"");
            }
        }
        return $result;
    }
}
