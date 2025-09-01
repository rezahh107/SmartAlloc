<?php // phpcs:ignoreFile
namespace SmartAlloc\Infra;
interface DbPort {
    public function exec(string $sql, array $args = []): int;
}
final class WpdbAdapter implements DbPort {
    public function __construct(private \wpdb $wpdb) {}

    public function exec(string $sql, array $args = []): int {
        $prepared = \SmartAlloc\Services\DbSafe::mustPrepare($sql, $args);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query($prepared);
        return (int) $this->wpdb->rows_affected;
    }
}
interface CircuitStorage{public function get(string $key):array;public function put(string $key,array $state,int $ttl):void;public function clear(string $key):void;/** @return array<int,string> */public function keys():array;}
final class TransientCircuitStorage implements CircuitStorage{private string $prefix='smartalloc_cb_';private string $keysOption='smartalloc_cb_keys';public function get(string $key):array{$v=get_transient($this->prefix.$key);return is_array($v)?$v:[];}public function put(string $key,array $state,int $ttl):void{set_transient($this->prefix.$key,$state,$ttl);$k=$this->keys();if(!in_array($key,$k,true)){$k[]=$key;update_option($this->keysOption,$k);}}public function clear(string $key):void{delete_transient($this->prefix.$key);$k=array_values(array_filter($this->keys(),fn($x)=>$x!==$key));update_option($this->keysOption,$k);}public function keys():array{$v=get_option($this->keysOption,[]);return is_array($v)?$v:[];}}
