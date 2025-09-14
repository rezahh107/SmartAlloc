<?php

declare(strict_types=1);

namespace SmartAlloc\CLI;

use SmartAlloc\Debug\ReproBuilder;

use function function_exists;

final class DebugCommand
{
    /**
     * Handle `wp smartalloc debug pack`.
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
        if (function_exists('\\WP_CLI\\Utils\\get_flag_value')) {
            $id = \WP_CLI\Utils\get_flag_value($assoc, 'id', '');
        } else {
            $id = $assoc['id'] ?? '';
        }
        if (function_exists('sanitize_text_field')) {
            $id = sanitize_text_field($id);
        }
        if ($id === '') {
            echo "missing id\n";
            return 1;
        }
        $builder = new ReproBuilder();
        $path = $builder->buildBundle($id); // @phpstan-ignore-line
        echo $path . "\n";
        return 0;
    }
}
