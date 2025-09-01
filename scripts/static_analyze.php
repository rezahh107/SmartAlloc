<?php
// phpcs:ignoreFile
declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

$dir = $argv[1] ?? '';
if ($dir === '') {
    echo "Usage: static-analyze.php <dir>\n";
    exit(1);
}

$parser = (new PhpParser\ParserFactory())->create(PhpParser\ParserFactory::PREFER_PHP7);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
$error_count = 0;
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    try {
        $parser->parse(file_get_contents($file->getPathname()));
    } catch (PhpParser\Error $e) {
        $error_count++;
    }
}

echo json_encode(['errors' => $error_count], JSON_THROW_ON_ERROR);
