<?php

declare(strict_types=1);

namespace SmartAlloc\Cli;

use function absint;
use function sanitize_text_field;
use function wp_json_encode;

final class ReviewCommand
{
    /**
     * Handle `wp smartalloc review`.
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
        $result = [];
        if (isset($assoc['approve'])) {
            $entry = absint($assoc['approve']);
            $mentor = absint($assoc['mentor'] ?? 0);
            $result = ['action' => 'approve', 'entry' => $entry];
            if ($mentor > 0) {
                $result['mentor'] = $mentor;
            }
        } elseif (isset($assoc['reject'])) {
            $entry = absint($assoc['reject']);
            $reason = sanitize_text_field($assoc['reason'] ?? '');
            $result = ['action' => 'reject', 'entry' => $entry, 'reason' => $reason];
        } else {
            echo "missing action\n";
            return 1;
        }
        if (($assoc['format'] ?? '') === 'json') {
            echo wp_json_encode($result) . "\n";
        } else {
            echo ($result['action'] ?? 'review') . "\n";
        }
        return 0;
    }
}
