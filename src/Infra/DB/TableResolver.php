<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\DB;

use wpdb;
use SmartAlloc\Core\FormContext;

final class TableResolver {
    public function __construct(private wpdb $db, private string $prefix = '') {
        $this->prefix = $prefix ?: $db->prefix;
    }
    public function allocations(FormContext $ctx): string { return "{$this->prefix}smartalloc_allocations{$ctx->suffix()}"; }
    public function runs(FormContext $ctx): string        { return "{$this->prefix}smartalloc_runs{$ctx->suffix()}"; }
    public function logs(FormContext $ctx): string        { return "{$this->prefix}smartalloc_logs{$ctx->suffix()}"; }
    public function dlq(FormContext $ctx): string         { return "{$this->prefix}smartalloc_dlq{$ctx->suffix()}"; }
}
