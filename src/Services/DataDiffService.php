<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;

final class DataDiffService {
    public function __construct(private TableResolver $tables) {}

    public function diff(FormContext $ctx, array $data): array {
        $tblAlloc = $this->tables->allocations($ctx);
        // Compare $data against records in $tblAlloc
        return ['form_id'=>$ctx->formId,'diff'=>[]];
    }
}
