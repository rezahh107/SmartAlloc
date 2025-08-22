<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\DlqService;

final class DlqServiceTest extends BaseTestCase
{
    private function setupDb(): void
    {
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';
            public array $rows = [];
            public int $auto = 1;
            public int $lastId = 0;
            public function query($sql){ /* no-op for START/COMMIT */ }
            public function insert($table, $data){ $data['id'] = $this->auto++; $this->rows[] = $data; }
            public function prepare($sql,...$args){
                $params = is_array($args[0] ?? null) ? $args[0] : $args;
                if (isset($params[0])) { $this->lastId = (int)$params[0]; }
                foreach ($params as $p) {
                    $sql = preg_replace('/%d/', (string)(int)$p, $sql, 1);
                    $sql = preg_replace('/%s/', "'".$p."'", $sql, 1);
                }
                return $sql;
            }
            public function get_results($sql,$mode){ return $this->rows; }
            public function get_row($sql,$mode){ foreach($this->rows as $r){ if($r['id']==$this->lastId){ return $r; } } return null; }
            public function delete($t,$w){ foreach($this->rows as $i=>$r){ if($r['id']==$w['id']){ unset($this->rows[$i]); } } }
        };
    }

    public function testPushListGetDelete(): void
    {
        $this->setupDb();
        $svc = new DlqService();
        $svc->push([
            'event_name' => 'Evt',
            'payload'    => ['b'=>2,'a'=>1],
            'attempts'   => 3,
            'error_text' => 'err',
        ]);
        $list = $svc->listRecent();
        $this->assertCount(1,$list);
        $this->assertSame(['a'=>1,'b'=>2], $list[0]['payload']);
        $id = $list[0]['id'];
        $item = $svc->get($id);
        $this->assertSame('Evt',$item['event_name']);
        $svc->delete($id);
        $this->assertCount(0,$svc->listRecent());
    }
}
