<?php

if (!defined('ABSPATH')) exit;

class TDAI_Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'toplevel_page_tdai-chatbot') {
            return;
        }

        wp_enqueue_style(
            'tdai-admin',
            TDAI_PLUGIN_URL . 'public/admin.css',
            [],
            TDAI_VERSION
        );

        wp_enqueue_script(
            'tdai-admin',
            TDAI_PLUGIN_URL . 'public/admin.js',
            [],
            TDAI_VERSION,
            true
        );

        wp_localize_script('tdai-admin', 'TDAIAdmin', [
            'restUrl' => esc_url_raw(rest_url('tdai-chat/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    public function menu()
    {
        add_menu_page(
            'Tech Delivery AI Chatbot',
            'TD AI Chatbot',
            'manage_options',
            'tdai-chatbot',
            [$this, 'page'],
            'dashicons-format-chat',
            80
        );
    }

    public function page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['tdai_save'])) {
            check_admin_referer('tdai_settings');

            $tdai_api_key = isset($_POST['tdai_api_key'])
                ? sanitize_text_field(wp_unslash($_POST['tdai_api_key']))
                : '';

            $tdai_chat_title = isset($_POST['tdai_chat_title'])
                ? sanitize_text_field(wp_unslash($_POST['tdai_chat_title']))
                : 'AI Chatbot';

            $tdai_welcome = isset($_POST['tdai_welcome'])
                ? sanitize_text_field(wp_unslash($_POST['tdai_welcome']))
                : 'Hei! Hva vil du vite?';

            $tdai_color = isset($_POST['tdai_color'])
                ? sanitize_hex_color(wp_unslash($_POST['tdai_color']))
                : '#9f241c';

            update_option(TDAI_OPENAI_API_KEY_OPTION, $tdai_api_key);
            update_option(TDAI_CHAT_TITLE_OPTION, $tdai_chat_title);
            update_option(TDAI_WELCOME_MESSAGE_OPTION, $tdai_welcome);
            update_option(TDAI_PRIMARY_COLOR_OPTION, $tdai_color);

            echo '<div class="notice notice-success"><p>Innstillingene er lagret.</p></div>';
        }

        $title   = get_option(TDAI_CHAT_TITLE_OPTION, 'AI Chatbot');
        $welcome = get_option(TDAI_WELCOME_MESSAGE_OPTION, 'Hei! Hva vil du vite?');
        $color   = get_option(TDAI_PRIMARY_COLOR_OPTION, '#9f241c');
        $apikey  = get_option(TDAI_OPENAI_API_KEY_OPTION, '');
        ?>

        <div class="wrap tdai-admin-wrap">
            <h1>SitePilot AI</h1>
            <p><strong>Free Version</strong> <?php echo esc_html(TDAI_VERSION); ?></p>

            <hr>

            <h2>Innstillinger</h2>

            <form method="post">
                <?php wp_nonce_field('tdai_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th>OpenAI API-key</th>
                        <td>
                            <input type="password" name="tdai_api_key" value="<?php echo esc_attr($apikey); ?>" class="tdai-input-wide">
                        </td>
                    </tr>

                    <tr>
                        <th>Chatbot-tittel</th>
                        <td>
                            <input type="text" name="tdai_chat_title" value="<?php echo esc_attr($title); ?>" class="tdai-input-wide">
                        </td>
                    </tr>

                    <tr>
                        <th>Velkomstmelding</th>
                        <td>
                            <input type="text" name="tdai_welcome" value="<?php echo esc_attr($welcome); ?>" class="tdai-input-wide">
                        </td>
                    </tr>

                    <tr>
                        <th>Hovedfarge</th>
                        <td>
                            <input type="color" name="tdai_color" value="<?php echo esc_attr($color); ?>">
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary" name="tdai_save" value="1">
                        Lagre innstillinger
                    </button>
                </p>
            </form>

            <hr>

            <h2>Indeksering</h2>

            <button type="button" class="button button-primary" id="tdai-ajax-index-button">
                Start indeksering
            </button>

            <div id="tdai-index-box" class="tdai-index-box">
                <div class="tdai-progress-track">
                    <div id="tdai-index-bar" class="tdai-progress-bar">0%</div>
                </div>

                <p id="tdai-index-status" class="tdai-index-status">Klar.</p>

                <p id="tdai-index-details" class="tdai-index-details">
                    0 / 0 sider - 0 innholdsblokker
                </p>

                <p id="tdai-index-url" class="tdai-index-url"></p>
            </div>

            <hr>

            <table class="widefat striped tdai-status-table">
                <tbody>
                    <tr>
                        <td><strong>OpenAI</strong></td>
                        <td><?php echo TDAI_OpenAI::has_api_key() ? esc_html('Tilkoblet') : esc_html('Ingen API-key'); ?></td>
                    </tr>

                    <tr>
                        <td><strong>Sist indeksert</strong></td>
                        <td><?php echo esc_html(get_option('tdai_last_indexed_at', 'Aldri')); ?></td>
                    </tr>

                    <tr>
                        <td><strong>Innholdsblokker</strong></td>
                        <td><?php echo intval(get_option('tdai_last_indexed_count', 0)); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="tdai-footer">
                <h2>SitePilot AI – Free Edition</h2>

                <p>
                    En AI-chatbot for WordPress som lar besøkende stille spørsmål og få svar basert på innholdet på nettstedet ditt.
                </p>

                <ul>
                    <li>Inkrementell indeksering</li>
                    <li>Klikkbare kilder</li>
                    <li>Responsivt chatvindu</li>
                    <li>Tilpassbar tittel, velkomstmelding og farge</li>
                </ul>

                <div class="tdai-footer-buttons">
                    <a href="https://techdelivery.no"
                       class="button button-secondary"
                       target="_blank"
                       rel="noopener noreferrer">
                        Besøk Tech Delivery
                    </a>

                    <a href="https://techdelivery.no/sitepilot-ai/"
                       class="button button-primary"
                       target="_blank"
                       rel="noopener noreferrer">
                        Oppgrader til Pro
                    </a>
                </div>

                <small>Version <?php echo esc_html(TDAI_VERSION); ?></small>
            </div>

            <p class="tdai-footer-credit">
                Powered by Tech Delivery AS &copy; 2026
            </p>
        </div>

        <?php
    }
}