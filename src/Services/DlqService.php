<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Infrastructure\Contracts\DlqRepository;
use SmartAlloc\Infrastructure\WpDb\WpDlqRepository;
use SmartAlloc\Perf\Stopwatch;
use SmartAlloc\Exception\ReplayException;
use SmartAlloc\Exceptions\RepositoryException;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

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
        try {
            $this->repo->insert(
                (string) ($entry['event_name'] ?? ''),
                $payload,
                new DateTimeImmutable('now', new DateTimeZone('UTC'))
            );
        } catch (Throwable $e) {
            $context = [
                'event_name'   => (string) ($entry['event_name'] ?? ''),
                'payload_size' => strlen((function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload))), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
            ];
            if ($this->logger) {
                $this->logger->error('dlq.push_failed', $context + ['error' => $e->getMessage()]);
            } else {
                \SmartAlloc\Support\LogHelper::error('dlq.push_failed', $context + ['error' => $e->getMessage()]);
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RepositoryException('Failed to push notification to DLQ', 'dlq_push', $context, $e);
        }
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
            try {
                $perf = Stopwatch::measure(function () use ($row): void {
                    $payload = [
                        'event_name' => (string) $row['event_name'],
                        'body'       => $row['payload'],
                        '_attempt'   => 1,
                    ];
                    try {
                        \do_action('smartalloc_notify', $payload);
                        $this->delete((int) $row['id']);
                    } catch (Throwable $e) {
                        throw new ReplayException('Replay failed', 0, $e); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    }
                });
                if ($this->logger) {
                    $level = $perf->durationMs > self::REPLAY_BUDGET_MS ? 'warning' : 'info';
                    $this->logger->$level('DlqService::doReplay iteration', [
                        'method'      => __METHOD__,
                        'row_id'      => $row['id'] ?? 'unknown',
                        'duration_ms' => $perf->durationMs,
                    ]);
                }
                $ok++;
            } catch (ReplayException $e) {
                $this->logReplayError($e, $row);
                $fail++;
            }
        }
        $depth = $this->count();
        return ['ok' => $ok, 'fail' => $fail, 'depth' => $depth];
    }

    /**
     * Log replay error with context.
     *
     * @param Throwable             $e   The caught exception
     * @param array<string,mixed>    $row The problematic row payload
     */
    private function logReplayError(Throwable $e, array $row): void
    {
        $prev        = $e->getPrevious();
        $message     = $prev?->getMessage() ?? $e->getMessage();
        $exception   = $prev ? get_class($prev) : get_class($e);
        $trace       = $prev?->getTraceAsString() ?? $e->getTraceAsString();
        $file        = $prev?->getFile() ?? $e->getFile();
        $line        = $prev?->getLine() ?? $e->getLine();
        $logContext  = [
            'method'            => __METHOD__,
            'row_id'            => $row['id'] ?? 'unknown',
            'event_name'        => $row['event_name'] ?? 'unknown',
            'attempts'          => $row['payload']['attempts'] ?? 0,
            'exception_message' => $message,
            'exception_class'   => $exception,
        ];

        if ($this->logger) {
            $this->logger->error('dlq.replay_failed', $logContext + [
                'trace' => $trace,
                'file'  => $file,
                'line'  => $line,
            ]);
        } else {
            \SmartAlloc\Support\LogHelper::error('dlq.replay_failed', $logContext);
        }
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
