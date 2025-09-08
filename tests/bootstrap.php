<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace {
foreach(['WP_TESTS_DOMAIN'=>'example.org','WP_TESTS_EMAIL'=>'admin@example.org','WP_TESTS_TITLE'=>'Test Blog','ABSPATH'=>'/tmp/wordpress/','WP_DEBUG'=>true,'WP_CONTENT_DIR'=>'/tmp/wordpress/wp-content','WP_PLUGIN_DIR'=>'/tmp/wordpress/wp-content/plugins','WPINC'=>'wp-includes','DB_NAME'=>'wordpress_test','DB_USER'=>'root','DB_PASSWORD'=>'','DB_HOST'=>'localhost','DB_CHARSET'=>'utf8','DB_COLLATE'=>''] as $k=>$v) if(!defined($k)) define($k,$v);
$table_prefix='wp_';
require_once dirname(__DIR__).'/vendor/autoload.php';
$patchwork = dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';
if ( file_exists( $patchwork ) ) {
	require_once $patchwork;
}

if ( ! class_exists( '\WP_Mock' ) ) {
	class WP_Mock {
		public static function bootstrap() {}
	}
}

\WP_Mock::bootstrap();

require_once __DIR__ . '/Helpers/TestHelpers.php';
require_once __DIR__ . '/Mocks/MockWpdb.php';
if ( ! class_exists( 'wpdb' ) ) {
	class wpdb extends SmartAlloc\Tests\Mocks\MockWpdb {}
}
global $wpdb;
$wpdb = new wpdb();
foreach ( [ 'OBJECT', 'ARRAY_A', 'ARRAY_N' ] as $c ) if ( ! defined( $c ) ) define( $c, $c );
if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	class WP_UnitTestCase extends PHPUnit\Framework\TestCase {}
}
$_wp_transients=$_wp_actions=$_wp_filters=$_wp_cache=[];
if(!function_exists('wp_verify_nonce')){function wp_verify_nonce($n,$a){return$n==='valid_nonce_'.$a;}}
if(!function_exists('wp_create_nonce')){function wp_create_nonce($a){return'valid_nonce_'.$a;}}
if(!function_exists('get_transient')){function get_transient($k){global $_wp_transients;return$_wp_transients[$k]??false;}}
if(!function_exists('set_transient')){function set_transient($k,$v,$e){global $_wp_transients;$_wp_transients[$k]=$v;return true;}}
if(!function_exists('delete_transient')){function delete_transient($k){global $_wp_transients;unset($_wp_transients[$k]);return true;}}
if(!function_exists('wp_send_json')){function wp_send_json($r,$s=null){if($s)http_response_code($s);header('Content-Type:application/json');echo json_encode($r);exit;}}
if(!function_exists('wp_send_json_success')){function wp_send_json_success($d=null,$s=null){wp_send_json(['success'=>true,'data'=>$d],$s);}}
if(!function_exists('wp_send_json_error')){function wp_send_json_error($d=null,$s=null){wp_send_json(['success'=>false,'data'=>$d],$s);}}
if(!function_exists('wp_die')){function wp_die($m=''){echo$m;exit;}}
if(!function_exists('esc_html')){function esc_html($t){return htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8');}}
if(!function_exists('esc_attr')){function esc_attr($t){return htmlspecialchars((string)$t,ENT_QUOTES,'UTF-8');}}
if(!function_exists('esc_url')){function esc_url($u){return filter_var($u,FILTER_SANITIZE_URL);}}
if(!function_exists('wp_kses_post')){function wp_kses_post($d){return strip_tags($d,'<p><br><strong><em><ul><ol><li><a><img>');}}
if(!function_exists('sanitize_text_field')){function sanitize_text_field($s){return trim(strip_tags($s));}}
if(!function_exists('sanitize_email')){function sanitize_email($e){return filter_var($e, FILTER_SANITIZE_EMAIL) ?: '';}}
if(!function_exists('get_bloginfo')){function get_bloginfo($s=''){return $s==='version'?'6.8.0':'';}}
if(!function_exists('add_action')){function add_action($h,$c,$p=10,$a=1){global $_wp_actions;$_wp_actions[$h][]=[$c,$p,$a];return true;}}
if(!function_exists('add_filter')){function add_filter($h,$c,$p=10,$a=1){global $_wp_filters;$_wp_filters[$h][]=[$c,$p,$a];return true;}}
if(!function_exists('do_action')){function do_action($h,...$a){global $_wp_actions;if(isset($_wp_actions[$h]))foreach($_wp_actions[$h] as $cb)call_user_func_array($cb[0],$a);}}
if(!function_exists('get_option')){function get_option($o,$d=false){$m=['siteurl'=>'http://example.org','home'=>'http://example.org','blogname'=>'Test Blog','admin_email'=>'admin@example.org'];return$m[$o]??$d;}}
if(!function_exists('update_option')){function update_option($o,$v,$a=null){return true;}}
if(!function_exists('current_time')){function current_time($t='timestamp',$gmt=0){return $t==='timestamp'?time():date('Y-m-d H:i:s');}}
if(!function_exists('wp_date')){function wp_date($f,$ts=null,$tz=null){return date($f,$ts??time());}}
if(!function_exists('wp_cache_get')){function wp_cache_get($k,$g=''){global $_wp_cache;return$_wp_cache[$g][$k]??false;}}
if(!function_exists('wp_cache_set')){function wp_cache_set($k,$d,$g='',$e=0){global $_wp_cache;$_wp_cache[$g][$k]=$d;return true;}}
if(!function_exists('wp_cache_flush')){function wp_cache_flush(){global $_wp_cache;$_wp_cache=[];return true;}}
if(!class_exists('WP_Error')){class WP_Error{public array $errors=[], $error_data=[];public function __construct($c='',$m='',$d=''){if($c)$this->add($c,$m,$d);}public function add($c,$m,$d=''){ $this->errors[$c][]=$m;if($d)$this->error_data[$c]=$d;}public function get_error_code(){return array_key_first($this->errors)??'';}public function get_error_message($c=''){if($c==='')$c=$this->get_error_code();return $this->errors[$c][0]??'';}}}
if(!function_exists('is_wp_error')){function is_wp_error($t){return $t instanceof WP_Error;}}
if(!class_exists('WP_REST_Request')){class WP_REST_Request{private $params=[], $headers=[], $body='';public function __construct($method='GET',$route='',$params=[]){$this->params=is_array($method)?$method:$params;unset($route);}public function set_body($body){$this->body=$body;}public function get_body(){return $this->body;}public function get_json_params(){return !empty($this->params)?$this->params:(json_decode($this->body,true)?:[]);}public function get_params(){return $this->params;}public function get_param(string $k){return $this->params[$k]??null;}public function get_header($k){return $this->headers[$k]??'';}public function set_header($k,$v){$this->headers[$k]=$v;}}}
if(!class_exists('WP_REST_Response')){class WP_REST_Response{private $data, $status;public function __construct($data=[],$status=200){$this->data=$data;$this->status=$status;}public function get_data(){return $this->data;}public function get_status(){return $this->status;}}}
if(!function_exists('wp_parse_args')){function wp_parse_args($a,$d=[]){if(is_object($a))$a=get_object_vars($a);elseif(!is_array($a))parse_str($a,$a);return array_merge($d,$a);}}
if(!function_exists('wp_parse_str')){function wp_parse_str($s,&$a){parse_str($s,$a);$a=\SmartAlloc\Services\apply_filters('wp_parse_str',$a);}}
echo "WordPress test environment initialized\n";
}

namespace SmartAlloc\Services {
	if (!function_exists(__NAMESPACE__ . '\\apply_filters')) {
	    function apply_filters(string $hook, $value, ...$args) {
	        return \function_exists('apply_filters') ? \apply_filters($hook, $value, ...$args) : $value;
	    }
	}

	if (!function_exists(__NAMESPACE__ . '\\get_transient')) {
	    function get_transient(string $key) {
	        return \function_exists('get_transient') ? \get_transient($key) : false;
	    }
	}

	if (!function_exists(__NAMESPACE__ . '\\set_transient')) {
	    function set_transient(string $key, $value, int $expiration) {
	        return \function_exists('set_transient') ? \set_transient($key, $value, $expiration) : true;
	    }
	}

	if (!function_exists(__NAMESPACE__ . '\\wp_date')) {
	    function wp_date(string $format, $timestamp = null, $timezone = null) {
	        return \function_exists('wp_date') ? \wp_date($format, $timestamp, $timezone) : date($format, $timestamp ?? time());
	    }
	}
}
