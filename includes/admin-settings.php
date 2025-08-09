<?php
if (!defined('ABSPATH')) exit;

add_action('admin_init', function () {
    // ✅ Register settings
    register_setting('ai_simple_settings', 'ai_openai_api_key');
    register_setting('ai_simple_settings', 'ai_openai_assistant_id');
	register_setting('ai_simple_settings', 'ai_debug_mode');

    // ✅ Register WooCommerce API credentials
    register_setting('ai_simple_settings', 'ai_wc_api_url');
    register_setting('ai_simple_settings', 'ai_wc_consumer_key');
    register_setting('ai_simple_settings', 'ai_wc_consumer_secret');
});

add_action('admin_menu', function () {
    add_menu_page(
        'AI Simple Assistant',
        'AI Assistant',
        'manage_options',
        'ai-simple-assistant',
        'ai_simple_assistant_page',
        'dashicons-format-chat',
        6
    );

    add_submenu_page(
        'ai-simple-assistant',
        'Settings',
        'Settings',
        'manage_options',
        'ai-simple-settings',
        'ai_simple_settings_page'
    );
	
	add_submenu_page(
    'ai-simple-assistant',
    'Debug Log',
    'Debug Log',
    'manage_options',
    'ai-debug-log',
    'ai_debug_page'
	);
});

function ai_simple_settings_page() {
    ?>
    <div class="wrap">
        <h1>AI Assistant Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('ai_simple_settings'); ?>
            <table class="form-table">

                <!-- ✅ OpenAI API Key -->
                <tr>
                    <th scope="row">OpenAI API Key</th>
                    <td>
                        <input type="text" name="ai_openai_api_key" value="<?php echo esc_attr(get_option('ai_openai_api_key')); ?>" size="60">
                    </td>
                </tr>

                <!-- ✅ Assistant ID -->
                <tr>
                    <th scope="row">Assistant ID</th>
                    <td>
                        <input type="text" name="ai_openai_assistant_id" value="<?php echo esc_attr(get_option('ai_openai_assistant_id')); ?>" size="60">
                    </td>
                </tr>

                <!-- ✅ WooCommerce API URL -->
                <tr>
                    <th scope="row">WooCommerce API URL</th>
                    <td>
                        <input type="text" name="ai_wc_api_url" value="<?php echo esc_attr(get_option('ai_wc_api_url')); ?>" size="60">
                        <p class="description">Example: https://yourwebsite.com/wp-json/wc/v3</p>
                    </td>
                </tr>

                <!-- ✅ WooCommerce Consumer Key -->
                <tr>
                    <th scope="row">WooCommerce Consumer Key</th>
                    <td>
                        <input type="text" name="ai_wc_consumer_key" value="<?php echo esc_attr(get_option('ai_wc_consumer_key')); ?>" size="60">
                    </td>
                </tr>

                <!-- ✅ WooCommerce Consumer Secret -->
                <tr>
                    <th scope="row">WooCommerce Consumer Secret</th>
                    <td>
                        <input type="text" name="ai_wc_consumer_secret" value="<?php echo esc_attr(get_option('ai_wc_consumer_secret')); ?>" size="60">
                    </td>
                </tr>
				
				<tr>
				  <th>Enable Debug Mode</th>
				  <td>
					<input type="checkbox" name="ai_debug_mode" value="1" <?php checked(get_option('ai_debug_mode'), 1); ?>>
				  </td>
				</tr>

            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
