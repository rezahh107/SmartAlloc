#!/usr/bin/env php
<?php
// phpcs:ignoreFile
declare(strict_types=1);

if ($argc < 2) {
    echo "Usage: detect_superglobals.php <dir>\n";
    exit(1);
}
$dir = $argv[1];
$super = ['$_GET','$_POST','$_REQUEST','$_COOKIE','$_SERVER','$_FILES','$_ENV'];
$san   = ['sanitize_text_field','sanitize_textarea_field','sanitize_email','sanitize_key','esc_html','esc_attr','esc_url','intval','absint','floatval','doubleval','filter_input','wp_unslash','prepare'];
$flags = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($iter as $file) {
    if ($file->isDir()) {
        continue;
    }
    if (pathinfo($file->getFilename(), PATHINFO_EXTENSION) !== 'php') {
        continue;
    }
    $code = file_get_contents($file->getPathname());
    $tokens = token_get_all($code);
    $count  = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_VARIABLE && in_array($t[1], $super, true)) {
            $prev = '';
            for ($j = $i - 1; $j >= 0 && $j >= $i - 10; $j--) {
                $pt = $tokens[$j];
                if (is_array($pt)) {
                    if ($pt[0] === T_STRING) {
                        $prev = $pt[1];
                        break;
                    }
                    if (in_array($pt[0], [T_WHITESPACE, T_OPEN_TAG, T_CONSTANT_ENCAPSED_STRING, T_VARIABLE, T_LNUMBER, T_DNUMBER], true)) {
                        continue;
                    }
                } else {
                    if ($pt === '(' || $pt === '[') {
                        continue;
                    }
                    break;
                }
            }
            if (!in_array($prev, $san, true)) {
                $flags[] = ['file' => $file->getPathname(), 'line' => $t[2]];
            }
        }
    }
}

echo json_encode($flags);
