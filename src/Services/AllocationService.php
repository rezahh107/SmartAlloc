<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;

final class AllocationService {
    public function __construct(
        private TableResolver $tables,
        // ... other collaborators (repositories, fuzzy, ranking, stats, logger, etc.)
    ) {}

    /**
     * @return array{summary:array, allocations:array<int,array>}
     */
    public function allocate(FormContext $ctx, array $students): array {
        $tblAlloc = $this->tables->allocations($ctx);
        // 1) filter candidates per business rules (gender→group→school→center→manager)
        // 2) rank (ratio→allocations_new→mentor_id) with default capacity=60
        // 3) write results into $tblAlloc
        // 4) emit events/metrics/logs labeled with form_id
        // (Implement using existing domain helpers; behavior must remain identical.)
        return ['summary'=>['form_id'=>$ctx->formId,'count'=>\count($students)], 'allocations'=>[]];
    }
}
