<?php

declare(strict_types=1);

namespace SmartAlloc\CLI;

use function absint;
use function sanitize_text_field;
use function wp_json_encode;

final class AllocateCommand
{
    /**
     * Handle `wp smartalloc allocate`.
     *
     * @param array<int,string> $args
     * @param array<string,string> $assoc
     */
    public function __invoke(array $args, array $assoc): int
    {
        if (!current_user_can(SMARTALLOC_CAP)) { // @phpstan-ignore-line
            echo "forbidden\n";
            return 1;
        }
        $entry = absint($assoc['entry'] ?? 0);
        if ($entry <= 0) {
            echo "missing entry\n";
            return 1;
        }
        $mode = sanitize_text_field($assoc['mode'] ?? 'direct');
        if (!in_array($mode, ['direct','rest'], true)) {
            $mode = 'direct';
        }
        $result = ['entry' => $entry, 'mode' => $mode];
        if (($assoc['format'] ?? '') === 'json') {
            echo wp_json_encode($result) . "\n";
        } else {
            echo "allocated {$entry} via {$mode}\n";
        }
        return 0;
    }
}
