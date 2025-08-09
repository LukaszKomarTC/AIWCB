<?php
if (!defined('ABSPATH')) exit;

function ai_simple_assistant_page() {
    $api_key = get_option('ai_openai_api_key');
    $assistant_id = get_option('ai_openai_assistant_id');
    $thread_option = 'ai_simple_thread_id_' . get_current_user_id();

    // 🔄 Reset Thread
    if (isset($_POST['ai_reset_thread'])) {
        delete_option($thread_option);
        require_once plugin_dir_path(__FILE__) . 'ai-core.php';
        ai_log_event("🔄 Thread reset by user");

        if ($api_key && $assistant_id) {
            $resp = wp_remote_post("https://api.openai.com/v1/threads", [
                'headers' => [
                    'Authorization' => "Bearer $api_key",
                    'Content-Type'  => 'application/json',
                    'OpenAI-Beta'   => 'assistants=v2'
                ]
            ]);

            $data = json_decode(wp_remote_retrieve_body($resp), true);
            if (!empty($data['id'])) {
                update_option($thread_option, $data['id']);
                ai_send_context_with_instructions($data['id'], $api_key, $assistant_id);
                ai_log_event("🆕 New thread created after reset");
                echo '<div class="updated"><p>✅ Thread reset and new one created with context sent.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Thread reset, but failed to create new one: ' . esc_html(json_encode($data)) . '</p></div>';
            }
        }
    }

    if (!$api_key || !$assistant_id) {
        echo '<div class="notice notice-error"><p>⚠️ Please set your API Key and Assistant ID in Settings.</p></div>';
        return;
    }

    $response = '';

    if (!empty($_POST['ai_task'])) {
        $task = sanitize_text_field($_POST['ai_task']);
        require_once plugin_dir_path(__FILE__) . 'ai-core.php';
        $response = ai_simple_run_task($task, $api_key, $assistant_id);
    }
    ?>

    <div class="wrap">
        <h1>AI Assistant Chat</h1>

        <form method="post" style="margin-bottom:10px;">
            <button type="submit" name="ai_reset_thread" class="button">🔄 Reset Thread</button>
        </form>

        <form method="post">
            <textarea id="ai_task_input" name="ai_task" rows="3" style="width:100%;"></textarea><br><br>
            <button type="button" id="start-voice" class="button">🎤 Speak</button>
            <button type="submit" class="button button-primary">Send</button>
        </form>

        <?php if ($response): ?>
        <div id="assistant-response" style="margin-top:20px; padding:15px; background:#fff; border:1px solid #ccc; border-radius:6px; max-width:800px; white-space:pre-wrap;">
            <?php echo esc_html($response); ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    document.getElementById("start-voice").addEventListener("click", function() {
        if (!('webkitSpeechRecognition' in window)) {
            alert("Speech recognition not supported in this browser.");
            return;
        }
        const recognition = new webkitSpeechRecognition();
        recognition.lang = "en-US";
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
        recognition.start();
        recognition.onresult = function(event) {
            document.getElementById("ai_task_input").value = event.results[0][0].transcript;
        };
    });
    </script>
    <?php
}
