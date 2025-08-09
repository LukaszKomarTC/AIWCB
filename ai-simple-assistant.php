<?php
/*
Plugin Name: AI Simple Assistant
Description: A minimal plugin to talk with OpenAI Assistant (memory via threads).
Version: 1.0
Author: You
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/ai-core.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/task-panel.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-logs.php';
require_once plugin_dir_path(__FILE__) . 'includes/debug-panel.php';
