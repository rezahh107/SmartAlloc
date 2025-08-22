<?php

declare(strict_types=1);

namespace SmartAlloc\CLI;

use WP_CLI;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\ExportService;

final class Commands {
    public function __construct(private AllocationService $alloc, private ExportService $export) {}

    /**
     * wp smartalloc run allocate --form=150
     */
    public function run_allocate(array $args, array $assoc): void {
        $form = (int)($assoc['form'] ?? 0);
        if ($form <= 0) { WP_CLI::error('Missing --form'); }
        $ctx = new FormContext($form);
        $res = $this->alloc->allocate($ctx, /* load students from GF or fixture */[]);
        WP_CLI::success('Allocated for form '.$form.' (count='.$res['summary']['count'].')');
    }

    /**
     * wp smartalloc run export --form=150 --out=/tmp/a.xlsx
     */
    public function run_export(array $args, array $assoc): void {
        $form = (int)($assoc['form'] ?? 0);
        $out  = (string)($assoc['out'] ?? '');
        if ($form <= 0 || $out === '') { WP_CLI::error('Missing --form or --out'); }
        $ctx = new FormContext($form);
        $path = $this->export->export($ctx, ['out'=>$out]);
        WP_CLI::success('Export written: '.$path);
    }
}
