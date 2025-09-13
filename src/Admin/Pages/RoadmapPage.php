<?php

declare(strict_types=1);

namespace SmartAlloc\Admin\Pages;

final class RoadmapPage
{
    public static function render(): void
    {
        $path = dirname(__DIR__, 3) . '/docs/ROADMAP-LIVE.json';
        $data = [];
        if (file_exists($path)) {
            $json = file_get_contents($path);
            $data = json_decode((string) $json, true) ?: [];
        }
        echo '<div class="wrap">';
        echo '<h1>SmartAlloc — Roadmap & KPIs</h1>';
        if (!$data) {
            echo '<p>Roadmap file not found or invalid.</p>';
            echo '<code>' . esc_html($path) . '</code>';
            echo '</div>';
            return;
        }
        $phase = esc_html((string) ($data['phase'] ?? 'unknown'));
        $updated = esc_html((string) ($data['updated_at'] ?? 'unknown'));
        $progress = isset($data['progress']) ? (int) round(((float) $data['progress']) * 100) : null;
        echo '<p><strong>Phase:</strong> ' . $phase . ' — <strong>Updated:</strong> ' . $updated . '</p>';
        if ($progress !== null) {
            echo '<p><strong>Progress:</strong> ' . $progress . '%</p>';
        }

        echo '<h2>Priorities</h2>';
        echo '<table class="widefat"><thead><tr><th>ID</th><th>Title</th><th>Owner</th><th>Status</th></tr></thead><tbody>';
        foreach ((array) ($data['priorities'] ?? []) as $p) {
            $id = esc_html((string) ($p['id'] ?? ''));
            $title = esc_html((string) ($p['title'] ?? ''));
            $owner = esc_html((string) ($p['owner'] ?? ''));
            $status = esc_html((string) ($p['status'] ?? ''));
            echo "<tr><td>{$id}</td><td>{$title}</td><td>{$owner}</td><td>{$status}</td></tr>";
        }
        echo '</tbody></table>';

        echo '<h2>KPIs</h2>';
        echo '<table class="widefat"><thead><tr><th>Group</th><th>Thresholds</th></tr></thead><tbody>';
        foreach ((array) ($data['kpis'] ?? []) as $group => $defs) {
            $pairs = [];
            foreach ((array) $defs as $k => $v) {
                $pairs[] = esc_html((string) $k) . '=' . esc_html(is_scalar($v) ? (string) $v : json_encode($v));
            }
            echo '<tr><td>' . esc_html((string) $group) . '</td><td>' . implode(', ', $pairs) . '</td></tr>';
        }
        echo '</tbody></table>';

        $doc = (string) ($data['links']['canonical_doc'] ?? '');
        if ($doc !== '') {
            echo '<p>Canonical Doc: <code>' . esc_html($doc) . '</code></p>';
        }
        echo '</div>';
    }
}

