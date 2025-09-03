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

    /** @return array<int,array<string,mixed>> */
    public function listRecent(int $limit): array
    {
        $out = [];
        foreach (array_slice(array_reverse($this->entries), 0, $limit) as $i => $e) {
            $out[] = [
                'id'         => $i + 1,
                'event_name' => $e['topic'],
                'payload'    => $e['payload'],
                'attempts'   => $e['payload']['attempts'] ?? 0,
                'error_text' => $e['payload']['error_text'] ?? null,
                'created_at' => $e['ts']->format('Y-m-d H:i:s'),
            ];
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function get(int $id): ?array
    {
        $items = $this->listRecent(PHP_INT_MAX);
        return $items[$id - 1] ?? null;
    }

    public function delete(int $id): void
    {
        $index = $id - 1;
        if (isset($this->entries[$index])) {
            unset($this->entries[$index]);
            $this->entries = array_values($this->entries);
        }
    }

    public function count(): int
    {
        return count($this->entries);
    }
}

