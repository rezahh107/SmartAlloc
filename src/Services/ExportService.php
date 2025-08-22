<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;

final class ExportService {
    public function __construct(private TableResolver $tables /* + writer, config, logger */) {}

    public function export(FormContext $ctx, array $options = []): string {
        $tblAlloc = $this->tables->allocations($ctx);
        // read allocations from $tblAlloc + mapping rules (postal_code_alias precedence)
        // build Excel according to existing exporter config; return file path
        return '/tmp/SabtExport-ALLOCATED-...xlsx';
    }
}
