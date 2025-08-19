<?php

declare(strict_types=1);

namespace SmartAlloc\Debug;

use SmartAlloc\Infra\Logging\Logger;
use Throwable;

/**
 * Collect PHP errors and store context for debugging.
 */
final class ErrorCollector
{
    private RedactionAdapter $redactor;
    private Logger $logger;
    /** @var array<string,int> */
    private static array $throttle = [];

    public function __construct(?RedactionAdapter $redactor = null, ?Logger $logger = null)
    {
        $this->redactor = $redactor ?? new RedactionAdapter();
        $this->logger = $logger ?? new Logger();
    }

    /** Register handlers when debugging enabled. */
    public function register(): void
    {
        if (!(bool) get_option('smartalloc_debug_enabled') || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /** @return bool */
    public function handleError(int $type, string $message, string $file, int $line): bool
    {
        $this->capture($type, $message, $file, $line, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
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
        $ctx = [
            'route' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'user_hash' => md5((string) (function_exists('get_current_user_id') ? get_current_user_id() : '0')),
            'correlation_id' => Logger::requestId(),
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
            'logs' => $logs,
            'queries' => $queries,
        ];
        $entry = $this->redactor->redact($entry);
        ErrorStore::add($entry);
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
            foreach (array_slice($wpdb->queries, -5) as $q) {
                $sql = is_array($q) ? $q[0] : (string) $q;
                if (preg_match('/%s|%d|\?|:\w+/', $sql)) {
                    $out[] = $sql;
                }
            }
        }
        return $out;
    }
}
