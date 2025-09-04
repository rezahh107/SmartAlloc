<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Infrastructure\Contracts\DlqRepository;
use SmartAlloc\Infrastructure\WpDb\WpDlqRepository;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use SmartAlloc\Perf\Stopwatch;
use SmartAlloc\Services\Exceptions\ReplayException;

/**
 * Dead letter queue storage service.
 */
final class DlqService
{
    private const REPLAY_BUDGET_MS = 150.0;

    public function __construct(private ?DlqRepository $repo = null, private ?LoggerInterface $logger = null)
    {
        $this->repo ??= WpDlqRepository::createDefault();
    }

    /**
     * Push an entry to the DLQ.
     *
     * @param array<string,mixed> $entry
     */
    public function push(array $entry): void
    {
        $payload = $this->maskSensitiveData(
            $this->sortRecursive((array) ($entry['payload'] ?? []))
        );
        $payload['attempts'] = (int) ($entry['attempts'] ?? 0);
        $this->repo->insert(
            (string) ($entry['event_name'] ?? ''),
            $payload,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }

    /**
     * List recent DLQ items.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listRecent(int $limit = 200): array
    {
        return $this->repo->listRecent($limit);
    }

    /**
     * Get single DLQ item.
     *
     * @return array<string,mixed>|null
     */
    public function get(int $id): ?array
    {
        return $this->repo->get($id);
    }

    public function delete(int $id): void
    {
        $this->repo->delete($id);
    }

    /** @return array{ok:int,fail:int,depth:int} */
    public function replay(int $limit = 100): array
    {
        $stats = $this->doReplay($limit);

        return [
            'ok'    => (int) ($stats['ok'] ?? 0),
            'fail'  => (int) ($stats['fail'] ?? 0),
            'depth' => (int) ($stats['depth'] ?? 0),
        ];
    }

    /** @return array{ok:int,fail:int,depth:int} */
    private function doReplay(int $limit): array
    {
        $rows = $this->listRecent($limit);
        $ok = 0;
        $fail = 0;
        foreach ($rows as $row) {
            $payload = [
                'event_name' => (string) $row['event_name'],
                'body'       => $row['payload'],
                '_attempt'   => 1,
            ];
            try {
                $perf = Stopwatch::measure(function () use ($payload, $row): void {
                    try {
                        \do_action('smartalloc_notify', $payload);
                        $this->delete((int) $row['id']);
                    } catch (\Throwable $e) {
                        throw new ReplayException($e->getMessage(), 0, $e); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    }
                });
                if ($this->logger) {
                    $level = $perf->durationMs > self::REPLAY_BUDGET_MS ? 'warning' : 'info';
                    $this->logger->$level('DlqService::doReplay row processed', [
                        'method'      => __METHOD__,
                        'row_id'      => $row['id'] ?? 'unknown',
                        'duration_ms' => $perf->durationMs,
                    ]);
                }
                $ok++;
            } catch (ReplayException $e) {
                $this->logReplayError($e, $row['id'] ?? 'unknown');
                $fail++;
            }
        }
        $depth = $this->count();
        return ['ok' => $ok, 'fail' => $fail, 'depth' => $depth];
    }

    private function logReplayError(\Throwable $e, int|string $rowId): void
    {
        if ($this->logger) {
            $this->logger->error('DlqService::doReplay failed for row', [
                'method'    => __METHOD__,
                'row_id'    => $rowId,
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            return;
        }
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(
            'DlqService::doReplay: Row ID ' . $rowId . ' - ' . $e->getMessage()
        );
    }

    private function count(): int
    {
        return $this->repo->count();
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function sortRecursive(array $data): array
    {
        ksort($data);
        foreach ($data as &$v) {
            if (is_array($v)) {
                $v = $this->sortRecursive($v);
            }
        }
        unset($v);
        return $data;
    }

    /**
     * Mask sensitive data before storage
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->maskSensitiveData($value);
                continue;
            }
            if ($key === 'to') {
                $value = $this->maskEmail((string) $value);
                continue;
            }
            if ($key === 'user_id') {
                $value = 'user_' . substr(hash('sha256', (string) $value), 0, 8);
                continue;
            }
            if (in_array($key, ['email', 'phone', 'ssn'], true)) {
                $value = '[REDACTED]';
            }
        }
        unset($value);
        return $data;
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '[INVALID_EMAIL]';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2));
        return $maskedLocal . '@' . $domain;
    }
}
