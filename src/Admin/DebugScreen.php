<?php

declare(strict_types=1);

namespace SmartAlloc\Admin;

use SmartAlloc\Debug\ErrorStore;
use SmartAlloc\Debug\PromptBuilder;
use SmartAlloc\Debug\ReproBuilder;
use function sanitize_text_field;
use function wp_create_nonce;

/**
 * Admin debug page to copy prompts.
 */
final class DebugScreen
{
    public static function render(): void
    {
        if (!current_user_can('smartalloc_manage')) {
            wp_die(esc_html__('Access denied', 'smartalloc'), '', ['response' => 403]);
        }
        $bundleRaw = filter_input(INPUT_GET, 'bundle', FILTER_SANITIZE_STRING);
        $bundle    = $bundleRaw ? sanitize_text_field(wp_unslash($bundleRaw)) : '';
        $nonceRaw  = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING);
        $nonce     = $nonceRaw ? wp_unslash($nonceRaw) : '';
        $action = $bundle ? 'smartalloc_debug_bundle' : 'smartalloc_debug';
        if (!wp_verify_nonce((string) $nonce, $action)) {
            wp_die(esc_html__('Invalid nonce', 'smartalloc'), '', ['response' => 403]);
        }
        if ($bundle !== '') {
            $path = (new ReproBuilder())->buildBundle($bundle);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename=' . basename($path));
            readfile($path);
            return;
        }
        $enabled = (bool) get_option('smartalloc_debug_enabled') && defined('WP_DEBUG') && WP_DEBUG;
        $entries = ErrorStore::all();
        $builder = new PromptBuilder();
        echo '<div class="wrap"><h1>Debug</h1>';
        if (!$enabled) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Debug mode disabled', 'smartalloc') . '</p></div>';
        }
        echo '<table class="wp-list-table"><tbody>';
        foreach ($entries as $entry) {
            $prompt = $builder->build($entry);
            $issue = $builder->buildIssue($entry);
            $hash = md5((string) ($entry['message'] ?? '') . (string) ($entry['file'] ?? '') . (string) ($entry['line'] ?? ''));
            $route = (string) ($entry['context']['route'] ?? '');
            $method = (string) ($entry['context']['method'] ?? '');
            $preview = mb_substr($prompt, 0, 2000);
            $truncated = mb_strlen($prompt) > 2000;
            if ($truncated) {
                $preview .= '…(truncated)';
            }
            echo '<tr>';
            echo '<td>' . esc_html($hash) . '</td>';
            echo '<td>' . esc_html($method . ' ' . $route) . '</td>';
            echo '<td>';
            echo '<pre class="prompt" data-full="' . esc_attr($prompt) . '">' . esc_html($preview) . '</pre>';
            if ($truncated) {
                echo '<button class="show-more">' . esc_html__('Show more', 'smartalloc') . '</button> ';
            }
            echo '<button class="copy-prompt" data-clip="' . esc_attr($prompt) . '">' . esc_html__('Copy Prompt', 'smartalloc') . '</button> ';
            echo '<button class="copy-issue" data-clip="' . esc_attr($issue) . '">' . esc_html__('Copy as GitHub Issue', 'smartalloc') . '</button> ';
            $bundleNonce = wp_create_nonce('smartalloc_debug_bundle');
            $url = '?page=smartalloc-debug&bundle=' . rawurlencode($hash) . '&_wpnonce=' . $bundleNonce;
            echo '<a class="button" href="' . esc_url($url) . '">' . esc_html__('Download Debug Bundle (.zip)', 'smartalloc') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        if (empty($entries)) {
            echo '<tr><td colspan="3">' . esc_html__('No errors', 'smartalloc') . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<div class="smartalloc-debug-evaluate">';
        echo '<h3>' . esc_html__('ارزیابی قوانین (Dry-Run)', 'smartalloc') . '</h3>';
        echo '<form id="sa-rule-evaluate">';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('smartalloc_rest')) . '">';
        echo '<input type="number" name="entry_id" placeholder="' . esc_attr__('Entry ID', 'smartalloc') . '" min="1" />';
        echo '<button type="submit" class="button button-primary">' . esc_html__('اجرا', 'smartalloc') . '</button>';
        echo '</form>';
        echo '<pre id="sa-evaluate-result" style="display:none;"></pre>';
        echo '</div>';
        echo '<script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "jQuery('#sa-rule-evaluate').on('submit',function(e){"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo 'e.preventDefault();const entryId=jQuery("[name=\"entry_id\"]").val();'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo 'const nonce=jQuery("[name=\"_wpnonce\"]").val();'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "jQuery.ajax({url:'" . esc_url_raw(rest_url('smartalloc/v1/rule-engine/evaluate')) . "',method:'POST',headers:{'X-WP-Nonce':nonce,'Content-Type':'application/json'},data:JSON.stringify({entry_id:parseInt(entryId)}),success:function(data){jQuery('#sa-evaluate-result').text(JSON.stringify(data,null,2)).show();},error:function(xhr){jQuery('#sa-evaluate-result').text('Error: '+xhr.responseText).show();}});"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '});'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<script>document.querySelectorAll(".copy-prompt,.copy-issue").forEach(b=>b.addEventListener("click",()=>navigator.clipboard.writeText(b.dataset.clip)));document.querySelectorAll(".show-more").forEach(b=>b.addEventListener("click",()=>{const p=b.previousElementSibling;p.textContent=p.dataset.full;b.remove();}));</script>';
        echo '</div>';
    }
}
