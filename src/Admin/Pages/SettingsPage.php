<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Pages;

use SmartAlloc\Infra\Settings\Settings;

final class SettingsPage
{
    public static function register(): void
    {
        register_setting('smartalloc_settings', 'smartalloc_settings', [
            'sanitize_callback' => [Settings::class, 'sanitize'],
        ]);
    }

    public static function render(): void
    {
        if (!current_user_can(SMARTALLOC_CAP)) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }

        /** @var array<string,mixed> $values */
        $values = (array) get_option('smartalloc_settings', []);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Settings', 'smartalloc') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
        settings_fields('smartalloc_settings');

        echo '<table class="form-table"><tbody>';

        self::numberField('fuzzy_auto_threshold', __('Fuzzy auto threshold', 'smartalloc'), $values, 'step="0.01" min="0" max="1"');
        self::numberField('fuzzy_manual_min', __('Fuzzy manual min', 'smartalloc'), $values, 'step="0.01" min="0" max="1"');
        self::numberField('fuzzy_manual_max', __('Fuzzy manual max', 'smartalloc'), $values, 'step="0.01" min="0" max="1"');
        self::numberField('default_capacity', __('Default capacity', 'smartalloc'), $values, 'min="1"');

        echo '<tr><th scope="row"><label for="allocation_mode">' . esc_html__('Allocation mode', 'smartalloc') . '</label></th><td>';
        $mode = $values['allocation_mode'] ?? 'direct';
        echo '<select id="allocation_mode" name="smartalloc_settings[allocation_mode]">';
        echo '<option value="direct"' . selected($mode, 'direct', false) . '>' . esc_html__('direct', 'smartalloc') . '</option>';
        echo '<option value="rest"' . selected($mode, 'rest', false) . '>' . esc_html__('rest', 'smartalloc') . '</option>';
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="postal_code_alias">' . esc_html__('Postal code alias rules', 'smartalloc') . '</label></th><td>';
        $aliases = $values['postal_code_alias'] ?? '[]';
        echo '<textarea id="postal_code_alias" name="smartalloc_settings[postal_code_alias]" rows="5" cols="50">' . esc_html($aliases) . '</textarea>';
        echo '</td></tr>';

        self::numberField('export_retention_days', __('Export retention days', 'smartalloc'), $values, 'min="0"');

        echo '</tbody></table>';
        submit_button();
        echo '</form></div>';
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function numberField(string $key, string $label, array $values, string $attrs): void
    {
        $value = $values[$key] ?? '';
        echo '<tr><th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="number" id="' . esc_attr($key) . '" name="smartalloc_settings[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" ' . $attrs . ' />';
        echo '</td></tr>';
    }
}

