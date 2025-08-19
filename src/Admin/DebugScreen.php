<?php

declare(strict_types=1);

namespace SmartAlloc\Admin;

use SmartAlloc\Debug\ErrorStore;
use SmartAlloc\Debug\PromptBuilder;

/**
 * Admin debug page to copy prompts.
 */
final class DebugScreen
{
    public static function render(): void
    {
        if (!current_user_can('manage_smartalloc')) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }
        $entries = ErrorStore::all();
        $builder = new PromptBuilder();
        echo '<div class="wrap"><h1>Debug</h1><table class="wp-list-table"><tbody>';
        foreach ($entries as $i => $entry) {
            $prompt = $builder->build($entry);
            $hash = md5((string) ($entry['message'] ?? '') . (string) ($entry['file'] ?? '') . (string) ($entry['line'] ?? ''));
            $route = '';
            if (isset($entry['context']) && is_array($entry['context']) && isset($entry['context']['route'])) {
                $route = (string) $entry['context']['route'];
            }
            echo '<tr>';
            echo '<td>' . esc_html($hash) . '</td>';
            echo '<td>' . esc_html($route) . '</td>';
            echo '<td><button class="copy-prompt" data-prompt="' . esc_attr($prompt) . '">' . esc_html__('Copy Prompt', 'smartalloc') . '</button></td>';
            echo '</tr>';
        }
        if (empty($entries)) {
            echo '<tr><td colspan="3">' . esc_html__('No errors', 'smartalloc') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
