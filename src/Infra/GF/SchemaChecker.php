<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

final class SchemaChecker
{
    public static function check(int $formId): array
    {
        return ['status' => 'compatible', 'reasons' => []];
    }
}
