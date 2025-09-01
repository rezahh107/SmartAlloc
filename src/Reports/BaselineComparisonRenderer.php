<?php
// phpcs:ignoreFile
namespace SmartAlloc\Reports;
class BaselineComparisonRenderer
{
    public function render(array $baseline): string
    {
        $date = $baseline['date_persian'] ?? ($baseline['project_baseline']['date_persian'] ?? '');
        $out = "\n## طبق «سند مبنا برای ادامه توسعه افزونه بر اساس وضعیت پروژه در تاریخ {$date}» موارد زیر تکمیل و باقی مانده\n\n";
        $out .= "| فاز | وظیفه | وضعیت | توضیحات |\n";
        $out .= "|-----|-------|--------|----------|\n";
        foreach ($baseline['phases'] ?? [] as $phase_name => $phase) {
            foreach ($phase['tasks'] ?? [] as $task_name => $task) {
                $status = $task['status'] ?? '';
                $icon = $this->icon($status);
                $desc = $task['description'] ?? '';
                $out .= sprintf("| %s | %s | %s %s | %s |\n", $phase_name, $task_name, $icon, $status, $desc);
            }
        }
        return $out;
    }
    private function icon(string $status): string
    {
        return match ($status) {
            'completed' => '🟢',
            'in-progress' => '🟡',
            'pending' => '🔴',
            default => '⚪',
        };
    }
}
