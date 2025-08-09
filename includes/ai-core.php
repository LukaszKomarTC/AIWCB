<?php

if (!defined('ABSPATH')) exit;

/** 🔹 Save debug log */
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

function ai_log_event($message) {
    $logs = get_option('ai_simple_logs', []);
    if (!is_array($logs)) $logs = [];
    $logs[] = [
        'time' => current_time('mysql'),
        'user_id' => get_current_user_id(),
        'event' => $message
    ];
    update_option('ai_simple_logs', $logs);
}

function ai_log_conversation($user_id, $prompt, $reply) {
    $logs = get_option('ai_simple_logs', []);
    if (!is_array($logs)) $logs = [];
    $logs[] = [
        'time' => current_time('mysql'),
        'user_id' => $user_id,
        'prompt' => $prompt,
        'reply' => $reply
    ];
    update_option('ai_simple_logs', $logs);
}

/** 🔹 Executes request prepared by Assistant */
function ai_execute_full_request($json) {
    ai_debug_log('received', "Assistant reply (raw): $json");

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

/** 🔹 Sends initial context */
function ai_send_context_with_instructions($thread_id, $api_key, $assistant_id) {
    $today = date("Y-m-d");
    $wc_url = get_option('ai_wc_api_url', '');

    $message = "Today is {$today}. Use this date to calculate 'tomorrow' or 'after tomorrow'.\n\n" .
               "WooCommerce API Base URL:\n" .
               "- {$wc_url}\n\n" .
               "📦 Orders:\n" .
               "- List orders: GET {base_url}/wc/v3/orders\n" .
               "- Create order: POST {base_url}/wc/v3/orders\n" .
               "- Delete order: DELETE {base_url}/wc/v3/orders/{id}\n\n" .
               "📅 Bookings:\n" .
               "- List bookings: GET {base_url}/wc-bookings/v1/bookings\n" .
               "- Create booking: POST {base_url}/wc-bookings/v1/bookings\n" .
               "- Delete booking: DELETE {base_url}/wc-bookings/v1/bookings/{id}\n\n" .
               "🛒 Products:\n" .
               "- List products: GET {base_url}/wc/v3/products\n" .
               "- Get product details: GET {base_url}/wc/v3/products/{id}\n" .
               "- Delete product: DELETE {base_url}/wc/v3/products/{id}\n\n";

    ai_debug_log('sent', "Sending context: $message");

    wp_remote_post("https://api.openai.com/v1/threads/$thread_id/messages", [
        'headers' => [
            'Authorization' => "Bearer $api_key",
            'Content-Type'  => 'application/json',
            'OpenAI-Beta'   => 'assistants=v2'
        ],
        'body' => json_encode([
            "role" => "user",
            "content" => $message
        ])
    ]);

    ai_log_event("♻️ Context with API details sent");
}

/** 🔹 Handles user tasks */
/** 🔹 Handles user tasks */
function ai_simple_run_task($task, $api_key, $assistant_id) {
    ai_debug_log('sent', "User message: $task");

    $user_id = get_current_user_id();
    $thread_option = 'ai_simple_thread_id_' . $user_id;
    $thread_id = get_option($thread_option);

    // 🔹 Create thread if it doesn't exist
    if (!$thread_id) {
        $resp = wp_remote_post("https://api.openai.com/v1/threads", [
            'headers' => [
                'Authorization' => "Bearer $api_key",
                'Content-Type'  => 'application/json',
                'OpenAI-Beta'   => 'assistants=v2'
            ]
        ]);

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        ai_debug_log('received', "Thread creation response: " . json_encode($data));

        if (!empty($data['id'])) {
            $thread_id = $data['id'];
            update_option($thread_option, $thread_id);
            ai_log_event("🆕 New thread created");
            ai_send_context_with_instructions($thread_id, $api_key, $assistant_id);
        } else {
            return "❌ Error creating thread: " . json_encode($data);
        }
    }

    // 🔹 Send user message
    wp_remote_post("https://api.openai.com/v1/threads/$thread_id/messages", [
        'headers' => [
            'Authorization' => "Bearer $api_key",
            'Content-Type'  => 'application/json',
            'OpenAI-Beta'   => 'assistants=v2'
        ],
        'body' => json_encode([
            "role" => "user",
            "content" => $task
        ])
    ]);

    // 🔹 Try creating a run
    $run_resp = wp_remote_post("https://api.openai.com/v1/threads/$thread_id/runs", [
        'headers' => [
            'Authorization' => "Bearer $api_key",
            'Content-Type'  => 'application/json',
            'OpenAI-Beta'   => 'assistants=v2'
        ],
        'body' => json_encode([
            "assistant_id" => $assistant_id
        ])
    ]);

    $run_data = json_decode(wp_remote_retrieve_body($run_resp), true);
    ai_debug_log('received', "Run creation response (1st attempt): " . json_encode($run_data));

    // 🔹 Retry once if run creation failed
    if (empty($run_data['id'])) {
        ai_debug_log('warning', "Run creation failed. Retrying once...");
        sleep(1); // short delay before retry

        $retry_resp = wp_remote_post("https://api.openai.com/v1/threads/$thread_id/runs", [
            'headers' => [
                'Authorization' => "Bearer $api_key",
                'Content-Type'  => 'application/json',
                'OpenAI-Beta'   => 'assistants=v2'
            ],
            'body' => json_encode([
                "assistant_id" => $assistant_id
            ])
        ]);

        $run_data = json_decode(wp_remote_retrieve_body($retry_resp), true);
        ai_debug_log('received', "Run creation response (retry): " . json_encode($run_data));
    }

    // 🔹 If still no run ID → return error
    if (empty($run_data['id'])) {
        return "❌ Error starting run (after retry): " . json_encode($run_data);
    }

    $run_id = $run_data['id'];

    // 🔹 Poll until completed
    for ($i = 0; $i < 10; $i++) {
        sleep(1);
        $check = wp_remote_get("https://api.openai.com/v1/threads/$thread_id/runs/$run_id", [
            'headers' => [
                'Authorization' => "Bearer $api_key",
                'OpenAI-Beta'   => 'assistants=v2'
            ]
        ]);
        $check_data = json_decode(wp_remote_retrieve_body($check), true);
        if (!empty($check_data['status']) && $check_data['status'] === 'completed') break;
    }

    // 🔹 Retrieve latest messages
    $msg_resp = wp_remote_get("https://api.openai.com/v1/threads/$thread_id/messages", [
        'headers' => [
            'Authorization' => "Bearer $api_key",
            'OpenAI-Beta'   => 'assistants=v2'
        ]
    ]);

    $messages = json_decode(wp_remote_retrieve_body($msg_resp), true);
    ai_debug_log('received', "Messages retrieved: " . json_encode($messages));

    $reply = $messages['data'][0]['content'][0]['text']['value'] ?? "❌ No response.";

    // 🔹 If reply is JSON → execute request
    $result = ai_execute_full_request($reply);
    if ($result !== null) {
        ai_debug_log('sent', "Sending API result to Assistant: " . json_encode($result));

        wp_remote_post("https://api.openai.com/v1/threads/$thread_id/messages", [
            'headers' => [
                'Authorization' => "Bearer $api_key",
                'Content-Type'  => 'application/json',
                'OpenAI-Beta'   => 'assistants=v2'
            ],
            'body' => json_encode([
                "role" => "user",
                "content" => "Here is the API result (in JSON). Summarize this result and display it in a clean, human-readable way:\n" . json_encode($result)
            ])
        ]);

        wp_remote_post("https://api.openai.com/v1/threads/$thread_id/runs", [
            'headers' => [
                'Authorization' => "Bearer $api_key",
                'Content-Type'  => 'application/json',
                'OpenAI-Beta'   => 'assistants=v2'
            ],
            'body' => json_encode([
                "assistant_id" => $assistant_id
            ])
        ]);

        return "✅ API call executed. Result: " . json_encode($result);
    }

    return $reply;
}


?>
