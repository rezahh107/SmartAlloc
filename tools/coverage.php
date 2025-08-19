<?php
declare(strict_types=1);

$info = strtolower(shell_exec('php -i'));
$hasDriver = str_contains($info, 'pcov') || str_contains($info, 'xdebug');
$optionalEnv = getenv('SA_COVERAGE_OPTIONAL');
$optional = $optionalEnv === false ? true : $optionalEnv !== '0';
if (!$hasDriver) {
    fwrite(STDERR, "No coverage driver (pcov or Xdebug) found.\n");
    fwrite(STDERR, "Hint: install pcov or enable Xdebug. Set SA_COVERAGE_OPTIONAL=0 to require coverage.\n");
    if ($optional) {
        exit(0);
    }
    exit(1);
}

if (!is_dir('build')) {
    mkdir('build', 0777, true);
}

passthru('vendor/bin/phpunit --coverage-clover build/coverage.xml', $code);
if ($code !== 0) {
    exit($code);
}

passthru('php tools/coverage-check.php build/coverage.xml', $code);
exit($code);
