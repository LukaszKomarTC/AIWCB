<?php
if (!defined('ABSPATH')) exit;

/** ◆ Save debug log */
function ai_debug_log($direction, $content) {
    if (!get_option('ai_debug_mode', false)) return;
    $logs = get_option('ai_debug_log', []);
    if (!is_array($logs)) $logs = [];
    $logs[] = [
        'time' => current_time('mysql'),
        'direction' => $direction,
        'content' => $content
    ];
    update_option('ai_debug_log', $logs);
}

/** ◆ Save simple event log */
function ai_log_event($message) {
    $logs = get_option('ai_simple_logs', []);
    if (!is_array($logs)) $logs = [];
    $logs[] = [
        'time' => current_time('mysql'),
        'user_id' => get_current_user_id(),
        'message' => $message
    ];
    update_option('ai_simple_logs', $logs);
}

/** ◆ Execute AI full request (with code fence stripping) */
function ai_execute_full_request($json) {
    ai_debug_log('received', "Assistant reply (raw): $json");

    // Preprocess: Strip code fences and trim
    $json = trim($json);
    // Remove triple backtick fences (```json ... ```)
    if (preg_match('/^```json\s*(.*)```$/s', $json, $m)) {
        $json = trim($m[1]);
    }
    // Remove any leftover triple backticks
    $json = preg_replace('/^```|```$/', '', $json);

    $data = json_decode($json, true);
    if (!$data || !isset($data['action']) || $data['action'] !== 'woocommerce_request') {
        return null;
    }

    $req = $data['request'];
    ai_debug_log('executed', "Executing API request: " . json_encode($req));

    // Add WooCommerce auth
    $ck = get_option('ai_wc_consumer_key');
    $cs = get_option('ai_wc_consumer_secret');
    $auth_header = 'Basic ' . base64_encode("$ck:$cs");

    $headers = isset($req['headers']) && is_array($req['headers']) ? $req['headers'] : [];
    $headers['Authorization'] = $auth_header;

    $args = [
        'method'  => $req['method'],
        'headers' => $headers,
        'body'    => !empty($req['body']) ? json_encode($req['body']) : null
    ];

    $response = wp_remote_request($req['url'], $args);

    $result = [
        'status' => wp_remote_retrieve_response_code($response),
        'body'   => json_decode(wp_remote_retrieve_body($response), true)
    ];

    ai_debug_log('executed', "API Response: " . json_encode($result));
    return $result;
}

/** ◆ Get current user info */
function ai_get_current_user_info() {
    $user_id = get_current_user_id();
    if (!$user_id) return null;
    $user = get_userdata($user_id);
    if (!$user) return null;
    return [
        'ID' => $user->ID,
        'user_login' => $user->user_login,
        'user_email' => $user->user_email,
        'display_name' => $user->display_name
    ];
}

/** ◆ Clear debug logs */
function ai_clear_debug_logs() {
    update_option('ai_debug_log', []);
}

/** ◆ Clear simple logs */
function ai_clear_simple_logs() {
    update_option('ai_simple_logs', []);
}
