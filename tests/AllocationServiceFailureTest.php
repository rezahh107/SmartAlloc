<?php
declare(strict_types=1);
use SmartAlloc\Tests\BaseTestCase;use SmartAlloc\Services\AllocationService;use SmartAlloc\Infra\DB\TableResolver;use SmartAlloc\Core\FormContext;use SmartAlloc\Services\Exceptions\{InsufficientCapacityException,DuplicateAllocationException};
if(!function_exists('current_time')){function current_time(){return '2024-01-01';}}if(!function_exists('sanitize_email')){function sanitize_email($v){return $v;}}if(!function_exists('sanitize_text_field')){function sanitize_text_field($v){return $v;}}if(!function_exists('get_user_by')){function get_user_by(){return true;}}
if(!class_exists('wpdb')){class wpdb{public $prefix='wp_';public function get_var($sql){return 0;}public function query($sql){}public function prepare($sql,...$a){return $sql;}}}
final class AllocationServiceFailureTest extends BaseTestCase{
public function test_no_capacity_returns_false():void{$db=new class extends wpdb{public function get_var($sql){return 60;}};global $wpdb;$wpdb=$db;$svc=new AllocationService(new TableResolver($db));$this->expectException(InsufficientCapacityException::class);$svc->allocateWithContext(new FormContext(150),array('student_id'=>1,'email'=>'a'));}
public function test_race_first_update_fails_second_succeeds():void{$db=new class extends wpdb{public $c=0;public function get_var($sql){$this->c++;return $this->c===1?1:0;}};global $wpdb;$wpdb=$db;$svc=new AllocationService(new TableResolver($db));$this->expectException(DuplicateAllocationException::class);$svc->allocateWithContext(new FormContext(150),array('student_id'=>1,'email'=>'a'));}
public function test_db_exception_bubbles():void{$db=new class extends wpdb{public function query($sql){throw new \RuntimeException('x');}};global $wpdb;$wpdb=$db;$svc=new AllocationService(new TableResolver($db));$this->expectException(\RuntimeException::class);$svc->allocateWithContext(new FormContext(150),array('student_id'=>1,'email'=>'a'));}
}
