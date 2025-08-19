<?php

declare(strict_types=1);

namespace SmartAlloc\Compat;

/**
 * Helper for temporarily removing and restoring WordPress filters.
 */
final class DateFilters
{
    /**
     * Locate callbacks on a hook that match the given predicate.
     *
     * @param callable $matcher receives the callback and should return true when matched
     * @return array<int, array{callback: callable, priority: int, accepted_args: int}>
     */
    public static function find(string $hook, callable $matcher): array
    {
        global $wp_filter;
        if (!isset($wp_filter[$hook])) {
            return [];
        }
        $found = [];
        if (isset($wp_filter[$hook]) && is_object($wp_filter[$hook]) && isset($wp_filter[$hook]->callbacks)) {
            $callbacks = $wp_filter[$hook]->callbacks;
        } else {
            $callbacks = (array) ($wp_filter[$hook] ?? []);
        }
        foreach ($callbacks as $priority => $functions) {
            foreach ($functions as $function) {
                $cb = $function['function'];
                if ($matcher($cb)) {
                    $found[] = [
                        'callback' => $cb,
                        'priority' => (int) $priority,
                        'accepted_args' => (int) ($function['accepted_args'] ?? 0),
                    ];
                }
            }
        }
        return $found;
    }

    /**
     * Remove matched callbacks from a hook.
     *
     * @param array<int, array{callback: callable, priority: int}> $callbacks
     */
    public static function remove(string $hook, array $callbacks): void
    {
        foreach ($callbacks as $cb) {
            remove_filter($hook, $cb['callback'], $cb['priority']);
        }
    }

    /**
     * Restore callbacks previously removed.
     *
     * @param array<int, array{callback: callable, priority: int, accepted_args: int}> $callbacks
     */
    public static function restore(string $hook, array $callbacks): void
    {
        foreach ($callbacks as $cb) {
            add_filter($hook, $cb['callback'], $cb['priority'], $cb['accepted_args']);
        }
    }
}
