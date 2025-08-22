<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Fixtures;

final class CrosswalkFactory
{
    public static function school(string $name, int $code): array
    {
        return ['name' => $name, 'code' => $code];
    }
}
