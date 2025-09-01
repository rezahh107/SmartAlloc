<?php declare(strict_types=1);
namespace SmartAlloc\Tests\Support;
use SmartAlloc\Infra\CircuitStorage;
final class ArrayCircuitStorage implements CircuitStorage{
    /** @var array<string,array> */private array $s=[];
    public function get(string $k):array{return $this->s[$k]??[];}
    public function put(string $k,array $st,int $ttl):void{$this->s[$k]=$st;}
    public function clear(string $k):void{unset($this->s[$k]);}
    public function keys():array{return array_keys($this->s);}
}
