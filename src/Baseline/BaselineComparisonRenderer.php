<?php
// src/Baseline/BaselineComparisonRenderer.php

declare(strict_types=1);

namespace SmartAlloc\Baseline;

class BaselineComparisonRenderer
{
    public function render(array $baseline): string
    {
        $date = $baseline['date_persian'] ?? '';
        $out = "\n## طبق «سند مبنا برای ادامه توسعه افزونه بر اساس وضعیت پروژه در تاریخ {$date}» موارد زیر تکمیل و باقی مانده\n\n";
        $out .= "| فاز | وظیفه | وضعیت | توضیحات |\n";
        $out .= "|-----|-------|--------|----------|\n";
        foreach ($baseline['phases'] as $phaseName => $phase) {
            foreach ($phase['tasks'] as $taskName => $task) {
                $icon = $this->iconForStatus($task['status'] ?? '');
                $out .= "| {$phaseName} | {$taskName} | {$icon} {$task['status']} | {$task['description']} |\n";
            }
        }
        return $out;
    }

    private function iconForStatus(string $status): string
    {
        return match ($status) {
            'completed' => '🟢',
            'in-progress' => '🟡',
            'pending' => '🔴',
            default => '⚪',
        };
    }
}
