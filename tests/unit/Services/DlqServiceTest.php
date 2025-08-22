<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\DlqService;

final class DlqServiceTest extends TestCase
{
    private function setupDb(): void
    {
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';
            public array $rows = [];
            public int $auto = 1;
            public function insert($table, $data){ $data['id']=$this->auto++; $this->rows[]=$data; }
            public function prepare($sql,...$args){ return $sql; }
            public function get_results($sql,$mode){ return array_map(fn($r)=>$r, $this->rows); }
            public function get_row($sql,$mode){ return $this->rows[0]??null; }
            public function delete($t,$w){ $this->rows=[]; }
        };
    }

    public function testPushListGetDeleteRetry(): void
    {
        $this->setupDb();
        $svc = new DlqService();
        $svc->push('Evt',['a'=>1],'err',3);
        $list = $svc->list();
        $this->assertCount(1,$list);
        $id = $list[0]['id'];
        $item = $svc->get($id);
        $this->assertSame('Evt',$item['event_name']);
        $ok = $svc->retry($id);
        $this->assertTrue($ok);
        $this->assertCount(0,$svc->list());
    }
}
