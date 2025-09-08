<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Exceptions\CircuitBreakerCallbackException;
use Psr\Log\AbstractLogger;

class TestLogger extends AbstractLogger{
    public array $records=[];
    public function log($l,$m,array $c=[]):void{$this->records[]=compact('l','m','c');}
    public function hasError(string $m):bool{foreach($this->records as $r){if($r['l']==='error'&&$r['m']===$m)return true;}return false;}
}

final class CircuitBreakerCallbackExceptionTest extends TestCase{
    private TestLogger $logger;
    protected function setUp():void{$this->logger=new TestLogger();$GLOBALS['_wp_transients']=[];}

    public function test_callback_exception_thrown_and_logged():void{
        $cb=new CircuitBreaker('t',$this->logger,5,function(){throw new \RuntimeException('cb');});
        \set_transient('smartalloc_circuit_breaker_t',['state'=>'open','fail_count'=>1,'cooldown_until'=>time()-1,'last_error'=>'x'],3600);
        $this->expectException(CircuitBreakerCallbackException::class);
        try{$cb->execute(fn()=> 'ok');}catch(CircuitBreakerCallbackException $e){$m=$cb->getFailureMetadata();$st=$cb->getStatus();$this->assertSame('callback',$m[0]['failure_type']);$this->assertTrue($this->logger->hasError('Circuit breaker callback failed'));$this->assertSame('open',$st->state);$this->assertGreaterThan(time(),$st->cooldownUntil);$this->assertSame(5,$st->failCount);throw $e;}}

    public function test_callback_exception_context():void{
        $orig=new \InvalidArgumentException('bad',4);
        $e=new CircuitBreakerCallbackException('m','t','c',$orig,0,$orig);
        $ctx=$e->getContext();
        $this->assertEquals('t',$ctx['callback_type']);
        $this->assertEquals('c',$ctx['circuit_name']);
        $this->assertEquals('InvalidArgumentException',$ctx['original_exception']['type']);
    }
}
