<?php

declare(strict_types=1);

namespace SmartAlloc\Admin;

use SmartAlloc\Migrations\FormTenantMigrator;
use SmartAlloc\Infra\GF\GFFormGenerator;
use SmartAlloc\Infra\GF\SchemaChecker;

final class FormsScreen
{
    public static function register(): void
    {
        add_submenu_page(
            'smartalloc-dashboard',
            esc_html__('SmartAlloc Forms', 'smartalloc'),
            esc_html__('Forms', 'smartalloc'),
            SMARTALLOC_CAP,
            'smartalloc_forms',
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!\SmartAlloc\Security\CapManager::canManage()) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }
        $forms = class_exists('GFAPI') ? \GFAPI::get_forms() : [];
        echo '<div class="wrap"><h1>SmartAlloc Forms</h1><table class="widefat"><thead><tr><th>ID</th><th>Title</th><th>Compatibility</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        global $wpdb;
        FormTenantMigrator::ensureRegistryTable($wpdb);
        $rows = $wpdb->get_results("SELECT form_id,status FROM {$wpdb->prefix}smartalloc_forms", ARRAY_A);
        $statuses = [];
        foreach ($rows as $r) {
            $statuses[(int)$r['form_id']] = $r['status'];
        }
        foreach ($forms as $form) {
            $id = (int)$form['id'];
            $title = esc_html($form['title']);
            $check = SchemaChecker::check($id);
            $compat = esc_html($check['status']);
            $status = esc_html($statuses[$id] ?? 'disabled');
            echo "<tr><td>{$id}</td><td>{$title}</td><td>{$compat}</td><td>{$status}</td><td>";
            if ($status === 'enabled') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('smartalloc_disable_form');
                echo '<input type="hidden" name="action" value="smartalloc_disable_form" />';
                echo '<input type="hidden" name="form_id" value="' . esc_attr((string)$id) . '" />';
                echo '<input type="submit" class="button" value="Disable" />';
                echo '</form>';
            } elseif ($check['status'] === 'compatible') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('smartalloc_enable_form');
                echo '<input type="hidden" name="action" value="smartalloc_enable_form" />';
                echo '<input type="hidden" name="form_id" value="' . esc_attr((string)$id) . '" />';
                echo '<input type="submit" class="button button-primary" value="Enable" />';
                echo '</form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('smartalloc_generate_gf_json');
        echo '<input type="hidden" name="action" value="smartalloc_generate_gf_json" />';
        echo '<input type="submit" class="button" value="Generate GF JSON" />';
        echo '</form></div>';
    }

    public static function handleEnable(): void
    {
        check_admin_referer('smartalloc_enable_form');
        if (!\SmartAlloc\Security\CapManager::canManage()) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }
        $raw = filter_input(INPUT_POST, 'form_id', FILTER_SANITIZE_NUMBER_INT);
        $formId = (int) wp_unslash((string) $raw);
        global $wpdb;
        FormTenantMigrator::ensureRegistryTable($wpdb);
        FormTenantMigrator::provisionFormTenant($wpdb, $formId);
        $t = $wpdb->prefix . 'smartalloc_forms';
        // @security-ok-sql
        $wpdb->replace($t, [
            'form_id' => $formId,
            'status' => 'enabled',
            'schema_version' => 'v1',
        ]);
        wp_safe_redirect(admin_url('admin.php?page=smartalloc_forms&enabled=' . $formId));
    }

    public static function handleDisable(): void
    {
        check_admin_referer('smartalloc_disable_form');
        if (!\SmartAlloc\Security\CapManager::canManage()) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }
        $raw = filter_input(INPUT_POST, 'form_id', FILTER_SANITIZE_NUMBER_INT);
        $formId = (int) wp_unslash((string) $raw);
        global $wpdb;
        FormTenantMigrator::ensureRegistryTable($wpdb);
        $t = $wpdb->prefix . 'smartalloc_forms';
        // @security-ok-sql
        $wpdb->update($t, ['status' => 'disabled'], ['form_id' => $formId]);
        wp_safe_redirect(admin_url('admin.php?page=smartalloc_forms&disabled=' . $formId));
    }

    public static function handleGenerateJson(): void
    {
        check_admin_referer('smartalloc_generate_gf_json');
        if (!\SmartAlloc\Security\CapManager::canManage()) {
            wp_die(esc_html__('Access denied', 'smartalloc'));
        }
        $json = GFFormGenerator::buildJson();
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="smartalloc-form-template.json"');
        echo $json;
    }
}
