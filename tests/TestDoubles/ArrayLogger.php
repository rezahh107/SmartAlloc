<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\TestDoubles;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class ArrayLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var array<int,array{level:string,message:string,context:array<string,mixed>}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => strtoupper((string) $level),
            'message' => $message,
            'context' => $context,
        ];
    }
}
