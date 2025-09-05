<?php
// phpcs:ignoreFile
declare(strict_types=1);
namespace SmartAlloc\Tests\Helpers;
use PHPUnit\Framework\TestCase as Base;
class TestCase extends Base{
protected function setUp():void{parent::setUp();$this->reset_globals();}
protected function tearDown():void{$this->reset_globals();parent::tearDown();}
private function reset_globals():void{global $wpdb;if(isset($wpdb)&&method_exists($wpdb,'flush')){$wpdb->flush();}}
protected function mockWordPressFunction(string $n,$r=true):void{if(!function_exists($n)){eval('function '.$n.'(){return '.var_export($r,true).';}');}}
}
