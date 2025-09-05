#!/usr/bin/env php
<?php
// phpcs:ignoreFile
$req=['Test Env'=>'composer validate:test-env','Unit Tests'=>'vendor/bin/phpunit --configuration phpunit-unit.xml --no-coverage','Code Standards'=>'vendor/bin/phpcs --standard=WordPress --extensions=php src tests --ignore=tests/Mocks'];
$p=0;$t=count($req);echo "=== SmartAlloc Baseline Foundation Phase Check ===\n";
foreach($req as $n=>$cmd){echo "$n...";exec($cmd.' 2>&1',$o,$r);echo $r?"FAIL\n":"OK\n";$p+=$r?0:1;$o=[];}
echo "$p/$t checks passed\n";exit($p===$t?0:1);
