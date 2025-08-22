<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;

final class HistoryService {
    public function __construct(private TableResolver $tables) {}

    public function record(FormContext $ctx, array $change): bool {
        $tblLogs = $this->tables->logs($ctx);
        // Write $change into $tblLogs
        return true;
    }
}
