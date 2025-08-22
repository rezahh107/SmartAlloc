<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\ScoringAllocator;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\EventStoreInterface;
use SmartAlloc\Domain\Allocation\AllocationResult;

final class AllocationServiceTest extends TestCase
{
    private function makeService(): AllocationService
    {
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';
            public int $rows_affected = 0;
            public array $params = [];
            public array $mentors = [
                2 => ['mentor_id'=>2,'gender'=>'M','center'=>'A','group_code'=>'G1','capacity'=>2,'assigned'=>1,'active'=>1,'allocations_new'=>0],
                3 => ['mentor_id'=>3,'gender'=>'M','center'=>'A','group_code'=>'G1','capacity'=>2,'assigned'=>1,'active'=>1,'allocations_new'=>0],
                4 => ['mentor_id'=>4,'gender'=>'M','center'=>'B','group_code'=>'G1','capacity'=>2,'assigned'=>0,'active'=>1,'allocations_new'=>0],
                5 => ['mentor_id'=>5,'gender'=>'F','center'=>'A','group_code'=>'G1','capacity'=>2,'assigned'=>0,'active'=>1,'allocations_new'=>0],
            ];
            public function prepare($sql,...$args){ $this->params = is_array($args[0]) ? $args[0] : $args; return $sql; }
            public function get_results($sql,$mode){
                [$gender,$center,$group] = $this->params;
                $out = array_filter($this->mentors, function($m) use($gender,$center,$group){
                    return $m['active']==1 && $m['assigned'] < $m['capacity'] && $m['gender']==$gender && $m['center']==$center && $m['group_code']==$group;
                });
                usort($out, fn($a,$b)=>$a['mentor_id']<=>$b['mentor_id']);
                return $out;
            }
            public function query($sql){
                if(preg_match('/mentor_id = (\d+)/',$sql,$m)){
                    $id=(int)$m[1];
                    if($this->mentors[$id]['assigned'] < $this->mentors[$id]['capacity']){
                        $this->mentors[$id]['assigned']++; $this->rows_affected=1;
                    } else { $this->rows_affected=0; }
                }
            }
            public function insert($t,$d){}
        };
        $logger = new Logging();
        $eventStore = new class implements EventStoreInterface {
            public function insertEventIfNotExists(string $e,string $k,array $p): int {return 1;}
            public function startListenerRun(int $e,string $l): int {return 1;}
            public function finishListenerRun(int $i,string $s,?string $er,int $d): void {}
            public function finishEvent(int $i,string $s,?string $e,int $d): void {}
        };
        $bus = new EventBus($logger,$eventStore);
        $metrics = new Metrics();
        return new AllocationService($logger,$bus,$metrics,new ScoringAllocator());
    }

    public function testFiltersRankingAndCommit(): void
    {
        $svc = $this->makeService();
        $res = $svc->assign(['id'=>7,'gender'=>'M','center'=>'A','group_code'=>'G1']);
        $this->assertInstanceOf(AllocationResult::class,$res);
        $this->assertSame(2,$res->get('mentor_id'));
        $this->assertSame(2,$GLOBALS['wpdb']->mentors[2]['assigned']);
    }
}
