<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SmartAlloc\Perf\Stopwatch;
use SmartAlloc\RuleEngine\RuleEngineService;
/** @group perf */
final class RuleEnginePerfTest extends TestCase{
public function test_p95_under_2s_for_1000_inputs():void{$e=new RuleEngineService();$s=[];for($i=0;$i<1000;$i++){$r=Stopwatch::measure(fn()=>$e->evaluate(['school_fuzzy'=>1.0]));$s[]=$r->durationMs;}$p=$this->pct($s,0.95);$this->assertLessThan(2000,$p);}
private function pct(array $d,float $p):float{sort($d);$i=(int)floor($p*count($d));$i=min(max($i,0),count($d)-1);return $d[$i];}
}
