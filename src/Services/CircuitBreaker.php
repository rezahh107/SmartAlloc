<?php // phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Infra\CircuitStorage;
use SmartAlloc\Infra\TransientCircuitStorage;
use SmartAlloc\ValueObjects\{CircuitState,CircuitStatus,CircuitBreakerStatus};
use DateTimeImmutable;
use DateTimeZone;

final class CircuitBreaker{
    private int $threshold;private int $cooldown;private $halfOpenCallback;private CircuitStorage $storage;
    public function __construct(int $threshold=5,int $cooldown=60,?callable $halfOpenCallback=null,?CircuitStorage $storage=null){$this->threshold=$threshold;$this->cooldown=$cooldown;$this->halfOpenCallback=$halfOpenCallback;$this->storage=$storage?:new TransientCircuitStorage();}
    private function getState(string $n):CircuitState{$r=$this->storage->get($n);if(!$r){return new CircuitState(CircuitStatus::CLOSED,0,null);} $dt=$r['opened_at']?new DateTimeImmutable($r['opened_at'],new DateTimeZone('UTC')):null;return new CircuitState(match($r['state']){'open'=>CircuitStatus::OPEN,'half'=>CircuitStatus::HALF,default=>CircuitStatus::CLOSED},(int)$r['failures'],$dt);}
    public function state(string $n):CircuitState{return $this->getState($n);}
    public function guard(string $name):void{$s=$this->getState($name);if($s->status()===CircuitStatus::OPEN){$opened=$s->lastFailureTime()?->getTimestamp()??0;$reset=$opened+$this->cooldown;if(time()<$reset){throw new \RuntimeException("Circuit breaker open: $name");}$this->setState($name,'half',0,null);}}
    public function success(string $n):void{$this->setState($n,'closed',0,null);}
    public function failure(string $n,\Throwable $e):void{$s=$this->getState($n);$f=$s->failureCount()+1;$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));if($f>=$this->threshold){$this->setState($n,'open',$f,$now->format('Y-m-d H:i:s'));}else{$this->setState($n,'half',$f,null);}}
    private function setState(string $n,string $state,int $f,?string $o):void{$this->storage->put($n,['state'=>$state,'failures'=>$f,'opened_at'=>$o],$this->cooldown);}
    public function reset(string $n):void{$this->setState($n,'closed',0,null);}
    public function protect(callable $op,string $svc,array $args=[]):mixed{$this->guard($svc);try{$res=$op(...$args);$this->success($svc);return $res;}catch(\Throwable $e){$this->failure($svc,$e);throw $e;}}
    public function getStatus():array{$st=[];foreach($this->storage->keys() as $n){$s=$this->getState($n);$o=$s->lastFailureTime();$ot=$o?$o->getTimestamp():null;$until=($s->status()===CircuitStatus::OPEN&&$ot)?$ot+$this->cooldown:null;$st[$n]=['state'=>$s->status()->value,'openedAt'=>$o?->format('Y-m-d H:i:s'),'failures'=>$s->failureCount(),'cooldownUntil'=>$until];}return $st;}
    public function getStatusSummary():array{$s=$this->getStatus();return ['circuits'=>$s,'summary'=>['total'=>count($s),'closed'=>count(array_filter($s,fn($x)=>$x['state']==='closed')),'open'=>count(array_filter($s,fn($x)=>$x['state']==='open')),'half'=>count(array_filter($s,fn($x)=>$x['state']==='half'))],'config'=>['threshold'=>$this->threshold,'cooldown'=>$this->cooldown,'has_half_open_callback'=>$this->halfOpenCallback!==null]];}
    public function executeHalfOpenCallback(string $n):mixed{if($this->halfOpenCallback===null){return null;}try{return call_user_func($this->halfOpenCallback,$n);}catch(\Throwable $e){return null;}}
    public function getConfig():array{return ['threshold'=>$this->threshold,'cooldown'=>$this->cooldown,'has_half_open_callback'=>$this->halfOpenCallback!==null];}
    public function updateConfig(int $t,int $c,$cb=null):void{$this->threshold=$t;$this->cooldown=$c;$this->halfOpenCallback=$cb;}
    public function getStatusDto(string $n):CircuitBreakerStatus{$s=$this->getState($n);$o=$s->lastFailureTime();$next=null;if($s->status()===CircuitStatus::OPEN&&$o){$next=$o->modify('+'.$this->cooldown.' seconds');}return new CircuitBreakerStatus($s->status()===CircuitStatus::OPEN,$s->failureCount(),$o,$next,$n);}
    public function getStatusReport(string $n):array{return ['service'=>$n,'status'=>$this->getStatusDto($n)];}
}

