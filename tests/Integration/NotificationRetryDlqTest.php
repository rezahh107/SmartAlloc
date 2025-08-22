<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\NotificationService;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Services\CircuitBreaker;

if (!class_exists('WP_Error')) { class WP_Error { public function __construct(public string $code = '', public string $message = '', public array $data = []) {} public function get_error_data(): array { return $this->data; } } }
if (!function_exists('add_action')) { function add_action($h,$c,$p=10,$a=1){} }
if (!function_exists('apply_filters')) { function apply_filters($t,$v,...$a){ global $filters; return isset($filters[$t]) ? $filters[$t]($v,...$a) : $v; } }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event($t,$h,$a){ } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($h,$a){ return false; } }
if (!function_exists('as_enqueue_async_action')) { function as_enqueue_async_action(){ return false; } }
if (!function_exists('as_next_scheduled_action')) { function as_next_scheduled_action(){ return false; } }

final class NotificationRetryDlqTest extends BaseTestCase
{
    public function testRetriesThenSucceeds(): void
    {
        global $filters; $filters = [];
        $wpdb = new class {
            public string $prefix = 'wp_';
            public array $dlq = [];
            public array $metrics = [];
            public function insert($table,$data){ if(str_contains($table,'dlq')){ $this->dlq[]=$data; } elseif(str_contains($table,'metrics')){ $this->metrics[]=$data; } }
            public function update($t,$d,$w){}
            public function get_row($sql){ return null; }
            public function replace($t,$d){ }
            public function prepare($sql,...$args){ return $sql; }
            public function query($sql){}
        };
        $GLOBALS['wpdb']=$wpdb;

        $breaker = new CircuitBreaker();
        $metrics = new Metrics();
        $service = new NotificationService($breaker, new Logging(), $metrics);

        $attempt=0;
        $filters['smartalloc_notify_transport']=function($val,$payload,$a) use (&$attempt){ $attempt++; if($attempt<=3){ throw new RuntimeException('fail'); } return true; };
        $payload=['event_name'=>'e','body'=>['x'=>1],'_attempt'=>1];
        $service->handle($payload);
        $payload['_attempt']=2; $service->handle($payload);
        $payload['_attempt']=3; $service->handle($payload);
        $payload['_attempt']=4; $service->handle($payload);
        $this->assertCount(0,$wpdb->dlq);
    }

    public function testPermanentFailureGoesToDlq(): void
    {
        global $filters; $filters = [];
        $wpdb = new class {
            public string $prefix='wp_';
            public array $dlq=[];
            public array $metrics=[];
            public function insert($table,$data){ if(str_contains($table,'dlq')){ $this->dlq[]=$data; } elseif(str_contains($table,'metrics')){ $this->metrics[]=$data; } }
            public function update($t,$d,$w){}
            public function get_row($sql){ return null; }
            public function replace($t,$d){ }
            public function prepare($sql,...$args){ return $sql; }
            public function query($sql){}
        };
        $GLOBALS['wpdb']=$wpdb;
        $breaker = new CircuitBreaker();
        $metrics=new Metrics();
        $service=new NotificationService($breaker,new Logging(),$metrics);
        $filters['smartalloc_notify_transport']=function($v,$p,$a){ throw new RuntimeException('nope'); };
        $payload=['event_name'=>'e','body'=>['y'=>2],'_attempt'=>1];
        for($i=1;$i<=6;$i++){ $payload['_attempt']=$i; $service->handle($payload); }
        $this->assertCount(1,$wpdb->dlq);
        $this->assertSame(6,$wpdb->dlq[0]['attempts']);
    }
}
