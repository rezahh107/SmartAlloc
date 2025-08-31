<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Rules;

final class AllocationFailureCode
{
    public const INVALID_INPUT = 'INVALID_INPUT';
    public const RULE_CONFIG = 'RULE_CONFIG';
    public const TIMEOUT = 'TIMEOUT';
    public const EXTERNAL_DEP = 'EXTERNAL_DEP';
}
