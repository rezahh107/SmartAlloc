<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\Settings;

use function sanitize_text_field;

/**
 * Plugin settings helper and sanitizer.
 */
final class Settings
{
    /** @var array<string,mixed> */
    private const DEFAULTS = [
        'fuzzy_auto_threshold'   => 0.90,
        'fuzzy_manual_min'       => 0.80,
        'fuzzy_manual_max'       => 0.89,
        'default_capacity'       => 60,
        'allocation_mode'        => 'direct',
        'postal_code_alias'      => '[]',
          'export_retention_days'  => 0,
          'log_retention_days'     => 30,
          'metrics_cache_ttl'      => 60,
          'webhook_secret'         => '',
          'enable_incoming_webhook' => 0,
      ];

    /**
     * Sanitize settings array.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function sanitize(array $input): array
    {
        $output = [];

        $output['fuzzy_auto_threshold'] = self::sanitizeFloat($input['fuzzy_auto_threshold'] ?? self::DEFAULTS['fuzzy_auto_threshold']);
        $output['fuzzy_manual_min']     = self::sanitizeFloat($input['fuzzy_manual_min'] ?? self::DEFAULTS['fuzzy_manual_min']);
        $output['fuzzy_manual_max']     = self::sanitizeFloat($input['fuzzy_manual_max'] ?? self::DEFAULTS['fuzzy_manual_max']);

        if (!($output['fuzzy_manual_min'] <= $output['fuzzy_manual_max'] && $output['fuzzy_manual_max'] < $output['fuzzy_auto_threshold'])) {
            $output['fuzzy_auto_threshold'] = self::DEFAULTS['fuzzy_auto_threshold'];
            $output['fuzzy_manual_min']     = self::DEFAULTS['fuzzy_manual_min'];
            $output['fuzzy_manual_max']     = self::DEFAULTS['fuzzy_manual_max'];
        }

        $output['default_capacity'] = self::absint($input['default_capacity'] ?? self::DEFAULTS['default_capacity']);
        if ($output['default_capacity'] < 1) {
            $output['default_capacity'] = self::DEFAULTS['default_capacity'];
        }

        $mode = (string) ($input['allocation_mode'] ?? self::DEFAULTS['allocation_mode']);
        $output['allocation_mode'] = in_array($mode, ['direct', 'rest'], true) ? $mode : 'direct';

        $aliasesRaw = (string) ($input['postal_code_alias'] ?? '');
        $decoded    = json_decode($aliasesRaw, true);
        $valid      = [];
        if (is_array($decoded)) {
            foreach ($decoded as $rule) {
                if (is_array($rule) && isset($rule['from'], $rule['to']) && is_string($rule['from']) && is_string($rule['to'])) {
                    $valid[] = ['from' => $rule['from'], 'to' => $rule['to']];
                }
            }
        }
        $output['postal_code_alias'] = json_encode($valid, JSON_UNESCAPED_UNICODE);

        $days = self::absint($input['export_retention_days'] ?? self::DEFAULTS['export_retention_days']);
          $output['export_retention_days'] = $days >= 0 ? $days : self::DEFAULTS['export_retention_days'];

          $logDays = self::absint($input['log_retention_days'] ?? self::DEFAULTS['log_retention_days']);
          $output['log_retention_days'] = $logDays >= 0 ? $logDays : self::DEFAULTS['log_retention_days'];

          $ttl = self::absint($input['metrics_cache_ttl'] ?? self::DEFAULTS['metrics_cache_ttl']);
          $output['metrics_cache_ttl'] = $ttl >= 0 ? $ttl : self::DEFAULTS['metrics_cache_ttl'];

          $output['webhook_secret'] = sanitize_text_field($input['webhook_secret'] ?? self::DEFAULTS['webhook_secret']);
          $output['enable_incoming_webhook'] = !empty($input['enable_incoming_webhook']) ? 1 : 0;

          return $output;
      }

    /**
     * Get allocation mode setting.
     */
    public static function getAllocationMode(): string
    {
        $mode = $GLOBALS['smartalloc_allocation_mode'] ?? null;
        if ($mode === null && defined('SMARTALLOC_ALLOCATION_MODE')) {
            $mode = constant('SMARTALLOC_ALLOCATION_MODE');
        }
        if ($mode === null) {
            $mode = self::get('allocation_mode');
        }
        return in_array($mode, ['direct', 'rest'], true) ? $mode : 'direct';
    }

    public static function getFuzzyAutoThreshold(): float
    {
        return (float) self::get('fuzzy_auto_threshold');
    }

    public static function getFuzzyManualMin(): float
    {
        return (float) self::get('fuzzy_manual_min');
    }

    public static function getFuzzyManualMax(): float
    {
        return (float) self::get('fuzzy_manual_max');
    }

    public static function getDefaultCapacity(): int
    {
        $capacity = (int) self::get('default_capacity');
        return $capacity >= 1 ? $capacity : self::DEFAULTS['default_capacity'];
    }

    /**
     * @return array<int,array{from:string,to:string}>
     */
    public static function getPostalCodeAliases(): array
    {
        $json = (string) self::get('postal_code_alias');
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

      public static function getExportRetentionDays(): int
      {
          $days = (int) self::get('export_retention_days');
          return $days >= 0 ? $days : 0;
      }

      public static function getLogRetentionDays(): int
      {
          $days = (int) self::get('log_retention_days');
          return $days >= 0 ? $days : 0;
      }

      public static function getMetricsCacheTtl(): int
      {
          $ttl = (int) self::get('metrics_cache_ttl');
          return $ttl >= 0 ? $ttl : (int) self::DEFAULTS['metrics_cache_ttl'];
      }

      public static function getWebhookSecret(): string
      {
          return (string) self::get('webhook_secret');
      }

      public static function isIncomingWebhookEnabled(): bool
      {
          return (bool) self::get('enable_incoming_webhook');
      }

    /**
     * Retrieve a raw setting with default fallback.
     */
    private static function get(string $key): mixed
    {
        $settings = (array) get_option('smartalloc_settings', []);
        return $settings[$key] ?? self::DEFAULTS[$key];
    }

    private static function sanitizeFloat(mixed $value): float
    {
        $v = is_numeric($value) ? (float) $value : 0.0;
        if ($v < 0) {
            $v = 0.0;
        }
        if ($v > 1) {
            $v = 1.0;
        }
        return $v;
    }

    private static function absint(mixed $value): int
    {
        return (int) abs((int) $value);
    }
}

