#!/usr/bin/env php
<?php
// phpcs:ignoreFile
$steps = [
['php -f tests/bootstrap-foundation.php', 'bootstrap'],
['vendor/bin/phpunit --configuration phpunit-foundation.xml --no-coverage', 'tests'],
['vendor/bin/phpcs --standard=WordPress src/Admin/DebugScreen.php', 'phpcs'],
];
$ok = true;
foreach ( $steps as $step ) {
[$cmd, $name] = $step;
system( $cmd, $code );
if ( 0 !== $code ) {
$ok  = false;
echo $name . " failed\n";
}
}
exit( $ok ? 0 : 1 );
