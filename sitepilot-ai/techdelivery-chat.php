<?php
/**
 * Plugin Name: Techdelivery Chat
 Description: AI-powered chatbot for WordPress that answers questions using your own website content.
 * Version: 1.0.2
 * Author: Tech Delivery AS
 * Author URI: https://techdelivery.no
 * License: GPL-2.0-or-later
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Text Domain: techdelivery-chat
 */

if (!defined('ABSPATH')) exit;

define('TDAI_VERSION', '1.0.1');
define('TDAI_PLUGIN_FILE', __FILE__);
define('TDAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TDAI_PLUGIN_URL', plugin_dir_url(__FILE__));

define('TDAI_EMBED_TABLE', 'tdai_chatbot_embeds');

define('TDAI_OPENAI_API_KEY_OPTION', 'tdai_openai_api_key');
define('TDAI_CHAT_TITLE_OPTION', 'tdai_chat_title');
define('TDAI_WELCOME_MESSAGE_OPTION', 'tdai_welcome_message');
define('TDAI_PRIMARY_COLOR_OPTION', 'tdai_primary_color');

require_once TDAI_PLUGIN_DIR . 'includes/class-database.php';
require_once TDAI_PLUGIN_DIR . 'includes/class-openai.php';
require_once TDAI_PLUGIN_DIR . 'includes/class-indexer.php';
require_once TDAI_PLUGIN_DIR . 'includes/class-rest.php';
require_once TDAI_PLUGIN_DIR . 'includes/class-admin.php';

final class TDAI_Chatbot {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(TDAI_PLUGIN_FILE, [$this, 'activate']);

        add_action('plugins_loaded', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_widget']);
    }

    public function init() {
        TDAI_Database::maybe_upgrade();
        new TDAI_Admin();
        new TDAI_REST();
    }

    public function activate()
{
    TDAI_Database::install();

    add_option(TDAI_CHAT_TITLE_OPTION, 'AI Chatbot');
    add_option(TDAI_WELCOME_MESSAGE_OPTION, 'Hei! Hva vil du vite?');
    add_option(TDAI_PRIMARY_COLOR_OPTION, '#9f241c');
}

    public function enqueue_widget() {
        if (is_admin()) return;
wp_enqueue_style(
    'tdai-chatbot-widget',
    TDAI_PLUGIN_URL . 'public/chat-widget.css',
    [],
    TDAI_VERSION
);
        wp_enqueue_script(
            'tdai-chatbot-widget',
            TDAI_PLUGIN_URL . 'public/chat-widget.js',
            [],
            TDAI_VERSION,
            true
        );

        wp_localize_script('tdai-chatbot-widget', 'TDAIChatbot', [
            'restUrl' => esc_url_raw(rest_url('tdai-chat/v1/ask')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'title'   => get_option(TDAI_CHAT_TITLE_OPTION, 'AI Chatbot'),
            'welcome' => get_option(TDAI_WELCOME_MESSAGE_OPTION, 'Hei! Hva vil du vite?'),
            'color'   => get_option(TDAI_PRIMARY_COLOR_OPTION, '#9f241c'),
            'credit'  => 'Powered by Tech Delivery AS'
        ]);
    }
}

TDAI_Chatbot::instance();
