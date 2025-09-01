<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\TestDoubles;

use SmartAlloc\Infrastructure\Contracts\DlqRepository;
use DateTimeImmutable;

final class SpyDlq implements DlqRepository
{
    /** @var array<int,array{topic:string,payload:array,ts:DateTimeImmutable}> */
    public array $entries = [];

    public function insert(string $topic, array $payload, DateTimeImmutable $createdAtUtc): void
    {
        $this->entries[] = ['topic' => $topic, 'payload' => $payload, 'ts' => $createdAtUtc];
    }

    public function has(string $topic): bool
    {
        foreach ($this->entries as $e) {
            if ($e['topic'] === $topic) {
                return true;
            }
        }
        return false;
    }

    public function last(string $topic): ?array
    {
        foreach (array_reverse($this->entries) as $e) {
            if ($e['topic'] === $topic) {
                return $e['payload'];
            }
        }
        return null;
    }
}

