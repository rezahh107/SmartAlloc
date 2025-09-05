#!/usr/bin/env php
<?php
// phpcs:ignoreFile
require __DIR__.'/../tests/bootstrap.php';
$tests=[
 'wpdb'=>fn()=>($GLOBALS['wpdb']->query('SELECT 1')===true),
 'nonce'=>fn()=>wp_verify_nonce('valid_nonce_test','test'),
 'transient'=>function(){set_transient('k','v',1);$r=get_transient('k');delete_transient('k');return $r==='v';},
 'hooks'=>function(){ $f=false;add_action('x',function()use(&$f){$f=true;});do_action('x');return $f;},
 'error'=>fn()=>is_wp_error(new WP_Error('c','d')),
];
$pass=0;$total=count($tests);echo "=== WordPress Test Environment Validation ===\n";
foreach($tests as $n=>$t){try{$t()? (++$pass&&print("$n: ok\n")) : print("$n: fail\n");}catch(Throwable $e){print("$n: fail\n");}}
echo "$pass/$total checks passed\n";exit($pass===$total?0:1);
