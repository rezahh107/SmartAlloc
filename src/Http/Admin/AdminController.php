<?php

namespace SmartAlloc\Http\Admin;

use SmartAlloc\Container;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\ExportService;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\Metrics;

final class AdminController
{
    private static ?Container $container = null;

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    public static function dashboard(): void
    {
        if (!self::$container) {
            echo '<div class="notice notice-error"><p>خطا: کانتینر تخصیص هوشمند در دسترس نیست.</p></div>';
            return;
        }

        try {
            $allocationService = self::$container->get(AllocationService::class);
            $metricsService = self::$container->get(Metrics::class);
            $exportService = self::$container->get(ExportService::class);

            $allocationStats = $allocationService->getStats();
            $metrics = $metricsService->getAll();
            $exportErrors = $exportService->getExportErrors();

            // Render dashboard view
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('داشبورد مدیریت تخصیص هوشمند', 'smartalloc') . '</h1>';
            echo '<p>' . esc_html__('خوش آمدید به داشبورد مدیریت تخصیص هوشمند. در اینجا می‌توانید وضعیت کلی سیستم را مشاهده کنید.', 'smartalloc') . '</p>';

            echo '<h2>' . esc_html__('آمار تخصیص', 'smartalloc') . '</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<caption class="screen-reader-text">' . esc_html__('آمار کلی تخصیص پشتیبان‌ها', 'smartalloc') . '</caption>';
            echo '<thead><tr>';
            echo '<th scope="col">' . esc_html__('کل پشتیبان‌ها', 'smartalloc') . '</th>';
            echo '<th scope="col">' . esc_html__('کل ظرفیت', 'smartalloc') . '</th>';
            echo '<th scope="col">' . esc_html__('تخصیص‌یافته', 'smartalloc') . '</th>';
            echo '<th scope="col">' . esc_html__('میانگین اشغال', 'smartalloc') . '</th>';
            echo '<th scope="col">' . esc_html__('تخصیص‌های امروز', 'smartalloc') . '</th>';
            echo '<th scope="col">' . esc_html__('ظرفیت باقیمانده', 'smartalloc') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            echo '<tr>';
            echo '<td>' . esc_html($allocationStats['total_mentors'] ?? 0) . '</td>';
            echo '<td>' . esc_html($allocationStats['total_capacity'] ?? 0) . '</td>';
            echo '<td>' . esc_html($allocationStats['total_assigned'] ?? 0) . '</td>';
            echo '<td>' . esc_html(round(($allocationStats['avg_occupancy'] ?? 0) * 100, 2)) . '%</td>';
            echo '<td>' . esc_html($allocationStats['today_allocations'] ?? 0) . '</td>';
            echo '<td>' . esc_html($allocationStats['available_capacity'] ?? 0) . '</td>';
            echo '</tr>';
            echo '</tbody>';
            echo '</table>';

            echo '<h2>متریک‌های سیستم</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>متریک</th><th>مقدار</th></tr></thead>';
            echo '<tbody>';
            foreach ($metrics as $key => $value) {
                echo '<tr><td>' . esc_html($key) . '</td><td>' . esc_html($value) . '</td></tr>';
            }
            echo '</tbody>';
            echo '</table>';

            if (!empty($exportErrors)) {
                echo '<h2>خطاهای اخیر اکسپورت</h2>';
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>ردیف</th><th>شیت</th><th>کد خطا</th><th>جزئیات خطا</th></tr></thead>';
                echo '<tbody>';
                foreach ($exportErrors as $error) {
                    echo '<tr>';
                    echo '<td>' . esc_html($error['row_idx'] ?? '') . '</td>';
                    echo '<td>' . esc_html($error['sheet'] ?? '') . '</td>';
                    echo '<td>' . esc_html($error['error_code'] ?? '') . '</td>';
                    echo '<td>' . esc_html($error['error_detail'] ?? '') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
            }

            echo '</div>';

        } catch (\Throwable $e) {
            echo '<div class="notice notice-error"><p>خطا در بارگذاری داشبورد: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    public static function settings(): void
    {
        // Handle form submission
        $nonce = filter_input(INPUT_POST, 'smartalloc_settings_nonce', FILTER_SANITIZE_STRING);
        $nonce = is_null($nonce) ? '' : wp_unslash($nonce);
        if ($nonce && wp_verify_nonce($nonce, 'smartalloc_save_settings')) {
            $formRaw = filter_input(INPUT_POST, 'smartalloc_form_id', FILTER_SANITIZE_NUMBER_INT);
            $formId  = $formRaw ? absint(wp_unslash($formRaw)) : 0;
            update_option('smartalloc_form_id', $formId);
            echo '<div class="notice notice-success is-dismissible"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
        }

        $currentFormId = get_option('smartalloc_form_id', 150); // Default to 150

        echo '<div class="wrap">';
        echo '<h1>تنظیمات تخصیص هوشمند</h1>';
        echo '<form method="post" action="">';
        wp_nonce_field('smartalloc_save_settings', 'smartalloc_settings_nonce');
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="smartalloc_form_id">فرم هدف Gravity Forms</label></th>';
        echo '<td>';
        echo '<input type="number" id="smartalloc_form_id" name="smartalloc_form_id" value="' . esc_attr($currentFormId) . '" class="regular-text" min="1" />';
        echo '<p class="description">شناسه فرم Gravity Forms که برای تخصیص دانش‌آموزان استفاده می‌شود.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        submit_button('ذخیره تغییرات');
        echo '</form>';
        echo '</div>';
    }

    public static function reports(): void
    {
        echo '<div class="wrap">';
        echo '<h1>گزارش‌های تخصیص هوشمند</h1>';
        echo '<p>این بخش شامل گزارش‌های مختلفی از جمله آمار تخصیص، عملکرد پشتیبان‌ها و غیره خواهد بود.</p>';
        // Future reports will be added here
        echo '</div>';
    }

    public static function logs(): void
    {
        if (!self::$container) {
            echo '<div class="notice notice-error"><p>خطا: کانتینر تخصیص هوشمند در دسترس نیست.</p></div>';
            return;
        }

        try {
            $loggingService = self::$container->get(Logging::class);
            $logContents = $loggingService->getLogContents();
            $logInfo = $loggingService->getLogInfo();

            echo '<div class="wrap">';
            echo '<h1>لاگ‌های تخصیص هوشمند</h1>';
            echo '<p>در اینجا می‌توانید لاگ‌های سیستم را مشاهده کنید. حجم فایل: ' . esc_html(size_format($logInfo['size'] ?? 0)) . '</p>';
            
            if ($logInfo['exists'] ?? false) {
                echo '<textarea style="width:100%; height: 600px; font-family: monospace; white-space: pre; overflow: auto; background-color: #f0f0f0; border: 1px solid #ccc;">' . esc_textarea($logContents) . '</textarea>';
            } else {
                echo '<p>فایل لاگ یافت نشد.</p>';
            }
            echo '</div>';

        } catch (\Throwable $e) {
            echo '<div class="notice notice-error"><p>خطا در بارگذاری لاگ‌ها: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
} 