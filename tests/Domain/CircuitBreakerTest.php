<?php
declare(strict_types=1);
use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Tests\Support\ArrayCircuitStorage;
final class CircuitBreakerTest extends BaseTestCase{
public function testCooldownUsesConfiguredValue():void{$s=new ArrayCircuitStorage();$b=new CircuitBreaker(threshold:1,cooldown:10,halfOpenCallback:null,storage:$s);$b->failure('svc',1);$st=$s->get('svc');$st['opened_at']=gmdate('Y-m-d H:i:s',time()-9);$s->put('svc',$st,0);$this->expectException(RuntimeException::class);$b->guard('svc');$st['opened_at']=gmdate('Y-m-d H:i:s',time()-11);$s->put('svc',$st,0);$b->guard('svc');$this->assertSame('half',$s->get('svc')['state']);}
public function testGetStatusSnapshotIsUTCAndAccurate():void{$s=new ArrayCircuitStorage();$b=new CircuitBreaker(threshold:1,cooldown:5,halfOpenCallback:null,storage:$s);$b->failure('api',1);$st=$b->getStatus();$this->assertArrayHasKey('api',$st);$snap=$st['api'];$this->assertSame('open',$snap['state']);$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$snap['openedAt']);$this->assertSame(1,$snap['failures']);$this->assertIsInt($snap['cooldownUntil']);$exp=strtotime($snap['openedAt'])+5;$this->assertSame($exp,$snap['cooldownUntil']);}
}
