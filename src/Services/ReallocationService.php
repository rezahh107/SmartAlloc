<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;

final class ReallocationService {
    public function __construct(private TableResolver $tables) {}

    public function reallocate(FormContext $ctx, array $students): array {
        $tblAlloc = $this->tables->allocations($ctx);
        // Perform reallocation logic against $tblAlloc
        return ['form_id'=>$ctx->formId,'count'=>\count($students)];
    }
}
