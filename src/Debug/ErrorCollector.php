<?php

declare(strict_types=1);

namespace SmartAlloc\Debug;

use SmartAlloc\Infra\Logging\Logger;
use SmartAlloc\Infra\Metrics\MetricsCollector;
use SmartAlloc\Security\InputRedactor;
use Throwable;

/**
 * Collect PHP errors and store context for debugging.
 */
final class ErrorCollector
{
    private RedactionAdapter $redactor;
    private Logger $logger;
    private MetricsCollector $metrics;
    /** @var array<string,int> */
    private static array $throttle = [];

    public function __construct(?RedactionAdapter $redactor = null, ?Logger $logger = null, ?MetricsCollector $metrics = null)
    {
        $this->redactor = $redactor ?? new RedactionAdapter();
        $this->logger = $logger ?? new Logger();
        $this->metrics = $metrics ?? new MetricsCollector();
    }

    /** Register handlers when debugging enabled. */
    public function register(): void
    {
        if (!(bool) get_option('smartalloc_debug_enabled') || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        set_error_handler([$this, 'handleError']); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /** @return bool */
    public function handleError(int $type, string $message, string $file, int $line): bool
    {
        $this->capture($type, $message, $file, $line, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
        return false;
    }

    public function handleException(Throwable $e): void
    {
        $this->capture(E_ERROR, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTrace());
    }

    public function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $this->capture($err['type'], $err['message'], $err['file'], $err['line'], []);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $trace
     */
    private function capture(int $type, string $message, string $file, int $line, array $trace): void
    {
        if (!(bool) get_option('smartalloc_debug_enabled')) {
            return;
        }
        $finger = md5($message . $file . $line);
        $now = time();
        if (isset(self::$throttle[$finger]) && ($now - self::$throttle[$finger]) < 300) {
            return;
        }
        self::$throttle[$finger] = $now;

        $stack = [];
        foreach (array_slice($trace, 0, 10) as $t) {
            $stack[] = ($t['file'] ?? 'unknown') . ':' . ($t['line'] ?? 0);
        }
        $server = InputRedactor::sanitizeServerArray($_SERVER);
        $ctx = [
            'route' => $server['REQUEST_URI'] ?? '',
            'method' => $server['REQUEST_METHOD'] ?? '',
            'user_hash' => md5((string) (function_exists('get_current_user_id') ? get_current_user_id() : '0')),
            'correlation_id' => Logger::requestId(),
            'timestamp' => gmdate('c'),
            'server' => $server,
        ];
        $env = [
            'php' => PHP_VERSION,
            /** @phpstan-ignore-next-line */
            'wp' => defined('ABSPATH') ? get_bloginfo('version') : 'unknown',
            'memory_limit' => ini_get('memory_limit'),
        ];
        $logs = array_slice($this->logger->records, -10);
        $queries = $this->collectQueries();

        $entry = [
            'type' => $type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'stack' => $stack,
            'context' => $ctx,
            'env' => $env,
            'breadcrumbs' => $logs,
            'queries' => $queries,
        ];
        $entry = $this->redactor->redact($entry);
        ErrorStore::add($entry);
        $this->metrics->inc('debug_error_captured_total');
    }

    /**
     * @return array<int,string>
     */
    private function collectQueries(): array
    {
        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            return [];
        }
        global $wpdb;
        $out = [];
        if (isset($wpdb->queries) && is_array($wpdb->queries)) {
            $tail = array_slice($wpdb->queries, -20);
            foreach (array_reverse($tail) as $q) {
                if (!is_array($q)) {
                    continue;
                }
                [$sql, , $trace] = $q + [null, null, null];
                if (!is_string($sql)) {
                    continue;
                }
                if (is_string($trace) && strpos($trace, 'wpdb->prepare') !== false) {
                    $out[] = $this->stripArgs($sql);
                    if (count($out) >= 5) {
                        break;
                    }
                }
            }
        }
        return $out;
    }

    private function stripArgs(string $sql): string
    {
        $sql = (string) preg_replace("/'[^']*'/", '?', $sql);
        $sql = (string) preg_replace('/\b\d+\b/', '?', $sql);
        return $sql;
    }
}
