<?php
if (!defined('ABSPATH')) exit;

function ai_debug_page() {
    $logs = get_option('ai_debug_log', []);
    if (!is_array($logs)) $logs = [];

    echo '<div class="wrap"><h1>AI Debug Log</h1>';
    echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Direction</th><th>Content</th></tr></thead><tbody>';

    foreach (array_reverse($logs) as $log) {
        echo '<tr>';
        echo '<td>' . esc_html($log['time']) . '</td>';
        echo '<td>' . esc_html($log['direction']) . '</td>';
        echo '<td><pre style="white-space: pre-wrap;">' . esc_html($log['content']) . '</pre></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}
