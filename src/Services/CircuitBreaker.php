<?php // phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Infra\CircuitStorage;
use SmartAlloc\Infra\TransientCircuitStorage;
use DateTimeImmutable;
use DateTimeZone;

final class CircuitBreaker{
    private int $threshold;private int $cooldown;private $halfOpenCallback;private CircuitStorage $storage;
    public function __construct(int $threshold=5,int $cooldown=60,?callable $halfOpenCallback=null,?CircuitStorage $storage=null){$this->threshold=$threshold;$this->cooldown=$cooldown;$this->halfOpenCallback=$halfOpenCallback;$this->storage=$storage?:new TransientCircuitStorage();}
    public function guard(string $name):void{$s=$this->getState($name);if($s['state']==='open'){$opened=(new DateTimeImmutable($s['opened_at'],new DateTimeZone('UTC')))->getTimestamp();$reset=$opened+$this->cooldown;if(time()<$reset){throw new \RuntimeException("Circuit breaker open: $name");}$this->setState($name,'half',0,null);}}
    public function success(string $n):void{$this->setState($n,'closed',0,null);}
    public function failure(string $n,int $threshold=5):void{$s=$this->getState($n);$f=$s['failures']+1;if($f>=$threshold){$this->setState($n,'open',$f,gmdate('Y-m-d H:i:s'));}else{$this->setState($n,'half',$f,null);}}
    private function getState(string $n):array{$r=$this->storage->get($n);if(!$r){return ['state'=>'closed','failures'=>0,'opened_at'=>null];}return $r;}
    private function setState(string $n,string $state,int $f,?string $o):void{$this->storage->put($n,['state'=>$state,'failures'=>$f,'opened_at'=>$o],$this->cooldown);}
    public function reset(string $n):void{$this->setState($n,'closed',0,null);}
    public function protect(callable $op,string $svc,array $args=[]):mixed{$this->guard($svc);try{$res=$op(...$args);$this->success($svc);return $res;}catch(\Throwable $e){$this->failure($svc,$this->threshold);throw $e;}}
    public function getStatus():array{$st=[];foreach($this->storage->keys() as $n){$r=$this->getState($n);$o=$r['opened_at'];$ot=$o?strtotime($o):null;$until=($r['state']==='open'&&$ot)?$ot+$this->cooldown:null;$st[$n]=['state'=>$r['state'],'openedAt'=>$o,'failures'=>$r['failures'],'cooldownUntil'=>$until];}return $st;}
    public function getStatusReport():array{$s=$this->getStatus();return ['circuits'=>$s,'summary'=>['total'=>count($s),'closed'=>count(array_filter($s,fn($x)=>$x['state']==='closed')),'open'=>count(array_filter($s,fn($x)=>$x['state']==='open')),'half'=>count(array_filter($s,fn($x)=>$x['state']==='half'))],'config'=>['threshold'=>$this->threshold,'cooldown'=>$this->cooldown,'has_half_open_callback'=>$this->halfOpenCallback!==null]];}
    public function executeHalfOpenCallback(string $n):mixed{if($this->halfOpenCallback===null){return null;}try{return call_user_func($this->halfOpenCallback,$n);}catch(\Throwable $e){return null;}}
    public function getConfig():array{return ['threshold'=>$this->threshold,'cooldown'=>$this->cooldown,'has_half_open_callback'=>$this->halfOpenCallback!==null];}
    public function updateConfig(int $t,int $c,$cb=null):void{$this->threshold=$t;$this->cooldown=$c;$this->halfOpenCallback=$cb;}
}
