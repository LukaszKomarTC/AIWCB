<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'ai-simple-assistant',
        'AI Assistant Logs',
        'Logs',
        'manage_options',
        'ai-assistant-logs',
        'ai_assistant_logs_page'
    );
});

function ai_assistant_logs_page() {
    $logs = get_option('ai_simple_logs', []);
    if (!is_array($logs)) $logs = [];

    echo '<div class="wrap"><h1>AI Assistant Logs</h1>';
    if (empty($logs)) {
        echo '<p>No logs yet.</p></div>';
        return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Time</th><th>User ID</th><th>Event / Prompt</th><th>Reply</th></tr></thead><tbody>';

    foreach (array_reverse($logs) as $log) {
        echo '<tr>';
        echo '<td>' . esc_html($log['time']) . '</td>';
        echo '<td>' . esc_html($log['user_id']) . '</td>';
        echo '<td>' . esc_html($log['event'] ?? $log['prompt'] ?? '') . '</td>';
        echo '<td>' . esc_html($log['reply'] ?? '') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}
