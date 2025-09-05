<?php
// phpcs:ignoreFile
declare(strict_types=1);
namespace SmartAlloc\Tests\Mocks;
class MockWpdb{public string $prefix='wp_',$base_prefix='wp_',$last_error='',$last_query='';public int $blogid=1,$siteid=1,$insert_id=0,$num_rows=0,$num_queries=0,$rows_affected=0;public array $last_result=[],$queries=[],$col_info=[], $mock_data=[];private int $next=1;public bool $show_errors=true;public function __construct(){ $this->mock_data=['wp_options'=>[['option_name'=>'siteurl','option_value'=>'http://example.org'],['option_name'=>'home','option_value'=>'http://example.org']],'wp_posts'=>[]]; }
public function query($q){$this->last_query=$q;$this->num_queries++;$t=strtoupper(substr(trim($q),0,6));if($t==='SELECT')return $this->handle_select($q);if($t==='INSERT')return $this->handle_insert($q);if($t==='UPDATE')return $this->handle_update($q);if($t==='DELETE')return $this->handle_delete($q);return true;}
private function handle_select($q){$res=[];if(strpos($q,'wp_options')!==false)$res=$this->mock_data['wp_options'];$this->last_result=array_map(fn($r)=>(object)$r,$res);$this->num_rows=count($this->last_result);return true;}
private function handle_insert($q){$this->insert_id=$this->next++;$this->rows_affected=1;return true;}
private function handle_update($q){$this->rows_affected=1;return true;}
private function handle_delete($q){$this->rows_affected=1;return true;}
public function get_results($q,$o=\OBJECT){$this->query($q);return $o===\ARRAY_A?array_map('get_object_vars',$this->last_result):$this->last_result;}
public function get_var($q,$c=0,$r=0){$this->query($q);$row=$this->last_result[$r]??null;return $row?array_values((array)$row)[$c]??null:null;}
public function get_row($q,$o){$this->query($q);$row=$this->last_result[0]??null;return $row?($o===\ARRAY_A?(array)$row:$row):null;}
public function get_col($q,$x=0){$this->query($q);$col=[];foreach($this->last_result as $row){$vals=array_values((array)$row);if(isset($vals[$x]))$col[]=$vals[$x];}return $col;}
public function insert($t,$d,$f=null){$this->last_query="INSERT INTO $t";$this->insert_id=$this->next++;$this->rows_affected=1;return true;}
public function update($t,$d,$w,$f=null,$wf=null){$this->last_query="UPDATE $t";$this->rows_affected=1;return 1;}
public function delete($t,$w,$wf=null){$this->last_query="DELETE FROM $t";$this->rows_affected=1;return 1;}
public function replace($t,$d,$f=null){return $this->insert($t,$d,$f);}
public function prepare(string $q,...$a): string{return $a?vsprintf(str_replace('%s','\'%s\'',$q),$a):$q;}
public function esc_like($t){return addcslashes($t,'_%\\');}
public function print_error($s=''){if($this->show_errors)echo"WordPress database error: $s\n";}
public function hide_errors(){ $this->show_errors=false;}
public function show_errors(){ $this->show_errors=true;}
public function flush(){ $this->last_result=[];$this->last_query='';$this->last_error='';}
public function check_connection($allow=true){return true;}
public function get_charset_collate(){return'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';}
public function add_mock_option($n,$v){$this->mock_data['wp_options'][]=['option_name'=>$n,'option_value'=>$v];}
public function clear_mock_data(){ $this->__construct();}
}
