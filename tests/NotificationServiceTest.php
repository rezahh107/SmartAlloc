<?php
declare(strict_types=1);
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\{NotificationService,CircuitBreaker,Logging,Metrics};
if(!defined('DAY_IN_SECONDS')){define('DAY_IN_SECONDS',86400);}if(!defined('SMARTALLOC_NOTIFY_MAX_TRIES')){define('SMARTALLOC_NOTIFY_MAX_TRIES',5);}if(!defined('SMARTALLOC_NOTIFY_BASE_DELAY')){define('SMARTALLOC_NOTIFY_BASE_DELAY',5);}if(!defined('SMARTALLOC_NOTIFY_BACKOFF_CAP')){define('SMARTALLOC_NOTIFY_BACKOFF_CAP',600);}if(!function_exists('add_action')){function add_action(){} }if(!function_exists('wp_mail')){function wp_mail(){global $mail_ok;return $mail_ok;}}
if(!function_exists('wp_json_encode')){function wp_json_encode($d){return json_encode($d);}}if(!function_exists('apply_filters')){function apply_filters($t,$v){return $v;}}
if(!function_exists('wp_upload_dir')){function wp_upload_dir(){return array('basedir'=>'/tmp');}}if(!function_exists('trailingslashit')){function trailingslashit($p){return rtrim($p,'/').'/';}}
if(!function_exists('as_enqueue_single_action')){function as_enqueue_single_action($ts,$h,$a,$g,$u){global $s;$s=array($ts,$h,$a);}}
if(!function_exists('as_enqueue_async_action')){function as_enqueue_async_action($h,$a,$g,$u){global $s;$s=array($h,$a);}}
if(!function_exists('wp_schedule_single_event')){function wp_schedule_single_event($ts,$h,$a){global $s;$s=array($ts,$h,$a);}}
if(!function_exists('get_transient')){function get_transient($k){global $t;return $t[$k]??false;}}if(!function_exists('set_transient')){function set_transient($k,$v,$e){global $t;$t[$k]=$v;}}
final class NotificationServiceTest extends BaseTestCase{
public function test_sendMail_success_sets_idempotency():void{global $mail_ok,$t;$mail_ok=true;$t=array();(new NotificationService(new CircuitBreaker(),new Logging(),new Metrics()))->sendMail(array('to'=>'a','subject'=>'s','message'=>'m'));$this->assertNotEmpty($t);}
public function test_sendMail_retry_on_failure():void{global $mail_ok,$s,$t;$mail_ok=false;$s=null;$t=array();(new NotificationService(new CircuitBreaker(),new Logging(),new Metrics()))->sendMail(array('to'=>'a','subject'=>'s','message'=>'m'));$this->assertSame('smartalloc_notify_mail',$s[1]);}
public function test_sendMail_drop_after_max():void{global $mail_ok,$s,$t;$mail_ok=false;$s=null;$t=array();(new NotificationService(new CircuitBreaker(),new Logging(),new Metrics()))->sendMail(array('to'=>'a','subject'=>'s','message'=>'m','_attempt'=>SMARTALLOC_NOTIFY_MAX_TRIES));$this->assertNull($s);}
}
