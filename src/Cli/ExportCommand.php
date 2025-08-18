<?php

declare(strict_types=1);

namespace SmartAlloc\Cli;

use function absint;
use function sanitize_text_field;
use function wp_json_encode;

final class ExportCommand
{
    /**
     * Handle `wp smartalloc export`.
     *
     * @param array<int,string> $args
     * @param array<string,string> $assoc
     */
    public function __invoke(array $args, array $assoc): int
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            echo "forbidden\n";
            return 1;
        }
        $from = sanitize_text_field($assoc['from'] ?? '');
        $to   = sanitize_text_field($assoc['to'] ?? '');
        $batch = isset($assoc['batch']) ? absint($assoc['batch']) : null;
        $result = ['from' => $from, 'to' => $to];
        if ($batch !== null) {
            $result['batch'] = $batch;
        }
        if (($assoc['format'] ?? '') === 'json') {
            echo wp_json_encode($result) . "\n";
        } else {
            echo "export {$from} {$to}\n";
        }
        return 0;
    }
}
