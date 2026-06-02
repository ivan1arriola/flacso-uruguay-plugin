<?php

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Integrations_Settings {
    private const PAGE_SLUG = 'flacso-integraciones';
    private const SETTINGS_GROUP = 'flacso_integraciones_group';

    private const OPTION_CONSULTAS_WEBHOOK_URL = 'fc_consultas_webhook_url';
    private const OPTION_UNIFIED_WEBHOOK_TOKEN = 'flacso_webhook_token';
    private const OPTION_INFO_REQUEST_WEBHOOK_URL = 'fc_oferta_webhook_url';
    private const OPTION_OFERTA_FLOTANTE_ENDPOINT = 'flacso_oferta_consulta_endpoint_url';
    private const OPTION_CHARLAS_WEBHOOK_URL = 'flacso_charlas_abiertas_webhook_url';
    private const OPTION_PREINSCRIPCIONES_WEBHOOK_URL = 'flacso_preinscripciones_webhook_url';
    private const OPTION_TELEGRAM_BOT_TOKEN = 'fc_telegram_bot_token';
    private const OPTION_TELEGRAM_CHAT_ID = 'fc_telegram_chat_id';
    private const OPTION_RECAPTCHA_SITE_KEY = 'fc_recaptcha_site_key';
    private const OPTION_RECAPTCHA_SECRET_KEY = 'fc_recaptcha_secret_key';
    private const OPTION_EXTERNAL_EDITOR_URL = 'flacso_external_editor_url';
    private const OPTION_MAILJET_API_KEY = 'flacso_mailjet_api_key';
    private const OPTION_MAILJET_SECRET_KEY = 'flacso_mailjet_secret_key';
    private const OPTION_MAILJET_LIST_ID = 'flacso_mailjet_list_id';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            'options-general.php',
            __('Integraciones FLACSO', 'flacso-uruguay'),
            __('Integraciones FLACSO', 'flacso-uruguay'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function register_settings(): void {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_CONSULTAS_WEBHOOK_URL,
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_UNIFIED_WEBHOOK_TOKEN,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_INFO_REQUEST_WEBHOOK_URL,
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_OFERTA_FLOTANTE_ENDPOINT,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_oferta_flotante_endpoint'],
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_CHARLAS_WEBHOOK_URL,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_charlas_webhook_url'],
                'default' => '',
            ]
        );


        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL,
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_TELEGRAM_BOT_TOKEN,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_TELEGRAM_CHAT_ID,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_RECAPTCHA_SITE_KEY,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_RECAPTCHA_SECRET_KEY,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_EXTERNAL_EDITOR_URL,
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => 'https://editor-flacso-uy.vercel.app',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_MAILJET_API_KEY,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_MAILJET_SECRET_KEY,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_MAILJET_LIST_ID,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_mailjet_list_id'],
                'default' => '',
            ]
        );
    }

    public static function sanitize_charlas_webhook_url($value): string {
        if (function_exists('flacso_charlas_abiertas_sanitize_webhook_url')) {
            return (string) flacso_charlas_abiertas_sanitize_webhook_url($value);
        }

        return (string) esc_url_raw($value);
    }

    public static function sanitize_charlas_webhook_token($value): string {
        if (function_exists('flacso_charlas_abiertas_sanitize_webhook_token')) {
            return (string) flacso_charlas_abiertas_sanitize_webhook_token($value);
        }

        return trim((string) $value);
    }

    public static function sanitize_oferta_flotante_endpoint($value): string {
        if (class_exists('Oferta_Consulta_Form') && is_callable(['Oferta_Consulta_Form', 'sanitize_endpoint_url'])) {
            return (string) Oferta_Consulta_Form::sanitize_endpoint_url($value);
        }

        return (string) esc_url_raw($value);
    }

    public static function sanitize_mailjet_list_id($value): string {
        return preg_replace('/[^0-9]/', '', (string) $value) ?: '';
    }

    public static function get_mailjet_settings(): array {
        return [
            'api_key' => trim((string) get_option(self::OPTION_MAILJET_API_KEY, '')),
            'secret_key' => trim((string) get_option(self::OPTION_MAILJET_SECRET_KEY, '')),
            'list_id' => trim((string) get_option(self::OPTION_MAILJET_LIST_ID, '')),
        ];
    }

    public static function is_mailjet_configured(): bool {
        $settings = self::get_mailjet_settings();

        return $settings['api_key'] !== ''
            && $settings['secret_key'] !== ''
            && $settings['list_id'] !== '';
    }

    public static function get_mailjet_contact_lists(bool $force_refresh = false): array {
        $settings = self::get_mailjet_settings();
        if ($settings['api_key'] === '' || $settings['secret_key'] === '') {
            return [];
        }

        $cache_key = 'flacso_mailjet_lists_' . md5($settings['api_key'] . '|' . $settings['secret_key']);
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = wp_remote_get(
            'https://api.mailjet.com/v3/REST/contactslist',
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($settings['api_key'] . ':' . $settings['secret_key']),
                    'Accept' => 'application/json',
                ],
            ]
        );

        if (is_wp_error($response)) {
            return [];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        $items = isset($decoded['Data']) && is_array($decoded['Data']) ? $decoded['Data'] : [];
        $lists = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $list_id = preg_replace('/[^0-9]/', '', (string) ($item['ID'] ?? ''));
            if ($list_id === '') {
                continue;
            }

            if (!empty($item['IsDeleted'])) {
                continue;
            }

            $lists[] = [
                'id' => $list_id,
                'name' => sanitize_text_field((string) ($item['Name'] ?? 'Lista sin nombre')),
                'subscribers' => absint($item['SubscriberCount'] ?? 0),
                'address' => sanitize_text_field((string) ($item['Address'] ?? '')),
            ];
        }

        usort($lists, static function (array $a, array $b): int {
            return strcasecmp($a['name'], $b['name']);
        });

        set_transient($cache_key, $lists, 5 * MINUTE_IN_SECONDS);

        return $lists;
    }

    public static function get_page_url(array $args = []): string {
        return add_query_arg(
            array_merge(
                [
                    'page' => self::PAGE_SLUG,
                ],
                $args
            ),
            admin_url('options-general.php')
        );
    }

    public static function get_redirect_url_from_request(array $args = [], string $default_url = ''): string {
        $base_url = $default_url !== '' ? $default_url : self::get_page_url();

        $requested = isset($_POST['redirect_to'])
            ? esc_url_raw(wp_unslash($_POST['redirect_to']))
            : '';

        if ($requested !== '') {
            $validated = wp_validate_redirect($requested, '');
            if ($validated !== '') {
                $base_url = $validated;
            }
        }

        return add_query_arg($args, $base_url);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <div class="flacso-integrations-dashboard">
                <header class="flacso-dashboard-header">
                    <div class="flacso-dashboard-title-area">
                        <h1 class="flacso-dashboard-title">
                            <?php esc_html_e('Integraciones FLACSO', 'flacso-uruguay'); ?>
                            <span class="flacso-badge">v2.0 – Centralizado</span>
                        </h1>
                    </div>
                    <p class="flacso-dashboard-subtitle">
                        <?php esc_html_e('Panel de control para centralizar, administrar y verificar de forma segura los endpoints y tokens unificados en todos los módulos del plugin FLACSO Uruguay.', 'flacso-uruguay'); ?>
                    </p>
                </header>

                <?php settings_errors(); ?>
                <?php self::render_inline_notices(); ?>

                <form method="post" action="options.php">
                    <?php settings_fields(self::SETTINGS_GROUP); ?>

                    <!-- Token de Acceso Global Único -->
                    <?php self::render_global_token_card(); ?>

                    <div class="flacso-integrations-grid">
                        <?php self::render_consultas_card(); ?>
                        <?php self::render_charlas_card(); ?>
                        <?php self::render_oferta_flotante_card(); ?>
                        <?php self::render_preinscripciones_card(); ?>
                        <?php self::render_external_editor_card(); ?>
                        <?php self::render_mailjet_card(); ?>
                        <?php self::render_services_card(); ?>
                    </div>

                    <div class="flacso-submit-section">
                        <?php submit_button(__('Guardar integraciones', 'flacso-uruguay')); ?>
                    </div>
                </form>

                <div class="flacso-integrations-tests">
                    <div class="flacso-section-title-area">
                        <h2>⚡ <?php esc_html_e('Pruebas de Conectividad Rápidas', 'flacso-uruguay'); ?></h2>
                        <p><?php esc_html_e('Ejecutá pruebas asíncronas para validar que las URLs y tokens unificados se comuniquen perfectamente.', 'flacso-uruguay'); ?></p>
                    </div>
                    <div class="flacso-integrations-test-grid">
                        <?php self::render_test_form(
                            'fc_test_consultas_webhook',
                            'fc_consultas_webhook_test_nonce',
                            'fc_test_consultas_webhook',
                            __('Probar consultas generales', 'flacso-uruguay'),
                            __('Valida el webhook del formulario de consulta general.', 'flacso-uruguay')
                        ); ?>
                        <?php self::render_test_form(
                            'fc_test_oferta_webhook',
                            'fc_oferta_webhook_test_nonce',
                            'fc_test_oferta_webhook',
                            __('Probar solicitud de información', 'flacso-uruguay'),
                            __('Valida el webhook usado por el bloque de solicitud de información.', 'flacso-uruguay')
                        ); ?>
                        <?php self::render_test_form(
                            'flacso_charlas_abiertas_test_webhook',
                            'flacso_charlas_abiertas_test_webhook_nonce',
                            'flacso_charlas_abiertas_test_webhook',
                            __('Probar charlas abiertas', 'flacso-uruguay'),
                            __('Valida el webhook de inscripciones de charlas abiertas.', 'flacso-uruguay')
                        ); ?>
                    </div>
                </div>

                <div class="flacso-integrations-links">
                    <h2>🔗 <?php esc_html_e('Accesos Directos Relacionados', 'flacso-uruguay'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=oferta-academica&page=flacso-oferta-consulta-form')); ?>">🌐 <?php esc_html_e('Formulario flotante de Oferta Académica', 'flacso-uruguay'); ?></a></li>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=flacso-preinscripciones')); ?>">📝 <?php esc_html_e('Preinscripciones', 'flacso-uruguay'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>

        <style>
            .flacso-integrations-dashboard {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                max-width: 1280px;
                margin: 20px auto 40px;
                padding: 0 10px;
            }

            /* Header styling */
            .flacso-dashboard-header {
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                color: #ffffff;
                padding: 35px 40px;
                border-radius: 16px;
                box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.15);
                margin-bottom: 30px;
                position: relative;
                overflow: hidden;
            }

            .flacso-dashboard-header::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -20%;
                width: 300px;
                height: 300px;
                background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 60%);
                pointer-events: none;
            }

            .flacso-dashboard-title-area {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 15px;
            }

            .flacso-dashboard-title {
                font-size: 28px;
                font-weight: 800;
                color: #ffffff;
                margin: 0;
                letter-spacing: -0.5px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .flacso-badge {
                background: rgba(59, 130, 246, 0.2);
                color: #60a5fa;
                border: 1px solid rgba(59, 130, 246, 0.3);
                padding: 4px 12px;
                border-radius: 9999px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .flacso-dashboard-subtitle {
                margin: 12px 0 0;
                font-size: 15px;
                color: #94a3b8;
                max-width: 800px;
                line-height: 1.6;
            }

            /* Global Token Card */
            .flacso-global-token-card {
                background: #ffffff;
                border: 2px solid #3b82f6;
                border-radius: 16px;
                padding: 30px;
                margin-bottom: 30px;
                box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.05), 0 4px 6px -4px rgba(59, 130, 246, 0.05);
                position: relative;
                overflow: hidden;
            }

            .flacso-global-token-card::after {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                width: 150px;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.02));
                pointer-events: none;
            }

            .flacso-global-token-header {
                display: flex;
                align-items: center;
                gap: 18px;
                margin-bottom: 20px;
                border-bottom: 1px solid #f1f5f9;
                padding-bottom: 18px;
            }

            .flacso-global-token-icon {
                font-size: 24px;
                width: 46px;
                height: 46px;
                border-radius: 12px;
                background: #eff6ff;
                color: #3b82f6;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .flacso-global-token-title-block h2 {
                font-size: 19px;
                font-weight: 800;
                color: #0f172a;
                margin: 0 0 4px !important;
            }

            .flacso-global-token-title-block p {
                margin: 0;
                color: #64748b;
                font-size: 14px;
                line-height: 1.5;
            }

            .flacso-global-token-body {
                max-width: 720px;
            }

            /* Grid & Cards */
            .flacso-integrations-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
                gap: 24px;
                margin-bottom: 24px;
            }

            .flacso-integrations-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 28px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
                transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.22s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .flacso-integrations-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 20px -8px rgba(15, 23, 42, 0.08), 0 4px 6px -2px rgba(15, 23, 42, 0.04);
                border-color: #cbd5e1;
            }

            .flacso-card-header {
                margin-bottom: 20px;
            }

            .flacso-card-icon-title {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 10px;
            }

            .flacso-card-icon-title h2 {
                font-size: 18px;
                font-weight: 700;
                color: #0f172a;
                margin: 0 !important;
            }

            .flacso-card-icon {
                font-size: 20px;
                width: 38px;
                height: 38px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* Card custom colors based on service type */
            .card-consultas .flacso-card-icon { background: #eff6ff; color: #3b82f6; }
            .card-charlas .flacso-card-icon { background: #faf5ff; color: #a855f7; }
            .card-flotante .flacso-card-icon { background: #fef2f2; color: #ef4444; }
            .card-preinscripciones .flacso-card-icon { background: #ecfdf5; color: #10b981; }
            .card-mailjet .flacso-card-icon { background: #effcf6; color: #16a34a; }
            .card-servicios .flacso-card-icon { background: #fff7ed; color: #f97316; }

            .flacso-integrations-card p.flacso-integrations-lead {
                color: #64748b;
                font-size: 13.5px;
                line-height: 1.5;
                margin: 0;
            }

            /* Field styling */
            .flacso-integrations-field {
                margin-bottom: 18px;
            }

            .flacso-integrations-field label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: #334155;
                margin-bottom: 6px;
            }

            .flacso-integrations-field input,
            .flacso-integrations-field select {
                width: 100%;
                max-width: none;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                padding: 10px 14px;
                font-size: 14px;
                color: #1e293b;
                background: #f8fafc;
                transition: all 0.2s ease-in-out;
            }

            .flacso-integrations-field input:focus,
            .flacso-integrations-field select:focus {
                border-color: #3b82f6;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
                outline: none;
            }

            .flacso-integrations-field input[disabled] {
                background: #e2e8f0;
                border-color: #cbd5e1;
                color: #475569;
                cursor: not-allowed;
                font-weight: 550;
            }

            .flacso-integrations-help {
                margin: 6px 0 0;
                font-size: 12px;
                color: #64748b;
                line-height: 1.45;
            }

            .flacso-integrations-note {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin-top: 15px;
                padding: 6px 12px;
                border-radius: 9999px;
                background: #f1f5f9;
                color: #475569;
                font-size: 11px;
                font-weight: 600;
                border: 1px solid #e2e8f0;
            }

            /* Submit Area */
            .flacso-submit-section {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 20px 30px;
                display: flex;
                justify-content: flex-end;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                margin-bottom: 40px;
            }

            .flacso-submit-section .submit {
                margin: 0 !important;
                padding: 0 !important;
            }

            .flacso-submit-section .button-primary {
                background: #2563eb !important;
                border: none !important;
                border-radius: 8px !important;
                padding: 12px 28px !important;
                font-size: 14px !important;
                font-weight: 600 !important;
                height: auto !important;
                line-height: 1.2 !important;
                box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25) !important;
                transition: all 0.2s ease !important;
                cursor: pointer;
            }

            .flacso-submit-section .button-primary:hover {
                background: #1d4ed8 !important;
                box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3) !important;
                transform: translateY(-1px);
            }

            /* Test Section styling */
            .flacso-integrations-tests {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 32px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                margin-top: 40px;
            }

            .flacso-section-title-area {
                margin-bottom: 25px;
                border-bottom: 1px solid #f1f5f9;
                padding-bottom: 18px;
            }

            .flacso-section-title-area h2 {
                font-size: 20px;
                font-weight: 800;
                color: #0f172a;
                margin: 0 0 6px;
            }

            .flacso-section-title-area p {
                margin: 0;
                color: #64748b;
                font-size: 14px;
            }

            .flacso-integrations-test-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }

            .flacso-integrations-test-card {
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 22px;
                background: #f8fafc;
                transition: all 0.22s ease;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .flacso-integrations-test-card:hover {
                border-color: #cbd5e1;
                background: #ffffff;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
            }

            .flacso-integrations-test-card h3 {
                font-size: 15px;
                font-weight: 700;
                color: #0f172a;
                margin: 0 0 8px;
            }

            .flacso-integrations-test-card p {
                font-size: 13px;
                color: #64748b;
                line-height: 1.45;
                margin: 0 0 20px;
                min-height: 40px;
            }

            .flacso-integrations-test-card .button-secondary {
                border: 1px solid #cbd5e1 !important;
                background: #ffffff !important;
                color: #334155 !important;
                border-radius: 8px !important;
                padding: 10px 16px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                height: auto !important;
                line-height: 1.2 !important;
                transition: all 0.2s ease !important;
                width: 100%;
                text-align: center;
                cursor: pointer;
            }

            .flacso-integrations-test-card .button-secondary:hover {
                background: #f1f5f9 !important;
                color: #0f172a !important;
                border-color: #94a3b8 !important;
            }

            /* Links Section */
            .flacso-integrations-links {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 30px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                margin-top: 30px;
            }

            .flacso-integrations-links h2 {
                font-size: 18px;
                font-weight: 700;
                color: #0f172a;
                margin: 0 0 15px;
            }

            .flacso-integrations-links ul {
                margin: 0;
                padding: 0;
                list-style: none;
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
            }

            .flacso-integrations-links li {
                margin: 0;
            }

            .flacso-integrations-links li a {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 20px;
                background: #f1f5f9;
                color: #334155;
                border-radius: 8px;
                text-decoration: none;
                font-size: 13px;
                font-weight: 600;
                transition: all 0.2s ease;
                border: 1px solid #e2e8f0;
            }

            .flacso-integrations-links li a:hover {
                background: #e2e8f0;
                color: #0f172a;
                transform: translateY(-1px);
            }

            /* Test loading and spinner */
            .flacso-test-loading {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
                font-weight: 600;
                color: #64748b;
                margin-top: 15px;
            }

            .flacso-test-loading::before {
                content: '';
                width: 14px;
                height: 14px;
                border: 2px solid #cbd5e1;
                border-top: 2px solid #3b82f6;
                border-radius: 50%;
                animation: flacso-spin 0.8s linear infinite;
            }

            @keyframes flacso-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const forms = document.querySelectorAll('.flacso-integrations-test-card');
                forms.forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const button = form.querySelector('input[type="submit"]');
                        const placeholder = form.querySelector('.flacso-test-result-placeholder');
                        const originalButtonVal = button.value;
                        
                        // Set loading state
                        button.disabled = true;
                        button.value = '<?php esc_attr_e('Ejecutando...', 'flacso-uruguay'); ?>';
                        placeholder.innerHTML = '<div class="flacso-test-loading"><?php esc_html_e('Conectando y validando...', 'flacso-uruguay'); ?></div>';
                        
                        const formData = new FormData(form);
                        const actionUrl = form.getAttribute('action');
                        
                        fetch(actionUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            const finalUrl = new URL(response.url);
                            let status = '';
                            let code = '';
                            let message = '';
                            let tokenVar = '';

                            if (finalUrl.searchParams.has('fc_consultas_webhook_test')) {
                                status = finalUrl.searchParams.get('fc_consultas_webhook_test');
                                code = finalUrl.searchParams.get('fc_consultas_webhook_code') || '';
                                message = finalUrl.searchParams.get('fc_consultas_webhook_message') || '';
                                tokenVar = 'FLACSO_WEBHOOK_TOKEN';
                            } else if (finalUrl.searchParams.has('fc_oferta_webhook_test')) {
                                status = finalUrl.searchParams.get('fc_oferta_webhook_test');
                                code = finalUrl.searchParams.get('fc_oferta_webhook_code') || '';
                                message = finalUrl.searchParams.get('fc_oferta_webhook_message') || '';
                                tokenVar = 'FLACSO_WEBHOOK_TOKEN';
                            } else if (finalUrl.searchParams.has('flacso_charlas_webhook_test')) {
                                status = finalUrl.searchParams.get('flacso_charlas_webhook_test');
                                code = finalUrl.searchParams.get('flacso_charlas_webhook_code') || '';
                                message = finalUrl.searchParams.get('flacso_charlas_webhook_message') || '';
                                tokenVar = 'FLACSO_WEBHOOK_TOKEN';
                            }

                            let noticeText = '';
                            if (status === 'success') {
                                noticeText = '<?php esc_html_e('La app respondió correctamente y aceptó la prueba del webhook.', 'flacso-uruguay'); ?>';
                            } else {
                                const codeNum = parseInt(code, 10);
                                if (codeNum === 401) {
                                    noticeText = '<?php esc_html_e('La app rechazó el token del webhook. Revisá que coincida con', 'flacso-uruguay'); ?> ' + tokenVar + '.';
                                } else if (codeNum === 404) {
                                    noticeText = '<?php esc_html_e('La URL del webhook respondió 404. Revisá que el endpoint exista en la app.', 'flacso-uruguay'); ?>';
                                } else if (codeNum >= 500) {
                                    noticeText = '<?php esc_html_e('La app respondió con un error interno (HTTP', 'flacso-uruguay'); ?> ' + codeNum + ').';
                                } else if (message) {
                                    noticeText = message;
                                } else {
                                    noticeText = '<?php esc_html_e('No se pudo validar la conexión con la app. Revisá la URL configurada y el token.', 'flacso-uruguay'); ?>';
                                }
                            }

                            if (status === 'success') {
                                placeholder.innerHTML = '<div class="flacso-test-result success">✅ <?php esc_html_e('Conexión Exitosa!', 'flacso-uruguay'); ?><br><span class="desc">' + noticeText + '</span></div>';
                            } else {
                                placeholder.innerHTML = '<div class="flacso-test-result error">❌ <?php esc_html_e('Fallo de Conexión', 'flacso-uruguay'); ?> ' + (code ? '(HTTP ' + code + ')' : '') + '<br><span class="desc">' + noticeText + '</span></div>';
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            placeholder.innerHTML = '<div class="flacso-test-result error">❌ <?php esc_html_e('Error de red', 'flacso-uruguay'); ?><br><span class="desc"><?php esc_html_e('No se pudo establecer comunicación con el servidor local o la app.', 'flacso-uruguay'); ?></span></div>';
                        })
                        .finally(() => {
                            button.disabled = false;
                            button.value = originalButtonVal;
                        });
                    });
                });
            });
        </script>
        <?php
    }

    private static function render_global_token_card(): void {
        ?>
        <div class="flacso-global-token-card">
            <div class="flacso-global-token-header">
                <span class="flacso-global-token-icon">🔑</span>
                <div class="flacso-global-token-title-block">
                    <h2><?php esc_html_e('Token de Autenticación Unificado', 'flacso-uruguay'); ?></h2>
                    <p><?php esc_html_e('Este token es la credencial única que valida la comunicación segura entre WordPress y la aplicación Next.js (FLACSO Editor) para todas las integraciones (Consultas, Ofertas y Charlas).', 'flacso-uruguay'); ?></p>
                </div>
            </div>
            <div class="flacso-global-token-body">
                <?php
                self::render_input_field(
                    self::OPTION_UNIFIED_WEBHOOK_TOKEN,
                    __('Token de Acceso Global', 'flacso-uruguay'),
                    'password',
                    __('Pega el token unificado aquí (coincidente con FLACSO_WEBHOOK_TOKEN)', 'flacso-uruguay'),
                    __('Se enviará automáticamente en las cabeceras (X-FLACSO-Webhook-Token) de todas las consultas y solicitudes de inscripción.', 'flacso-uruguay')
                );
                ?>
                <span class="flacso-integrations-note">🔑 <?php esc_html_e('Variable esperada en Next.js: FLACSO_WEBHOOK_TOKEN', 'flacso-uruguay'); ?></span>
            </div>
        </div>
        <?php
    }

    private static function render_consultas_card(): void {
        ?>
        <section class="flacso-integrations-card card-consultas">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">💬</span>
                    <h2><?php esc_html_e('Consultas en la app', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Agrupa el formulario general y el bloque de solicitud de información. Ambos usan el token unificado global.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_input_field(
                    self::OPTION_CONSULTAS_WEBHOOK_URL,
                    __('Formulario de consulta general', 'flacso-uruguay'),
                    'url',
                    'https://tu-dominio.com/api/consultas/general',
                    __('Sugerido para el formulario de consulta general del módulo Formularios.', 'flacso-uruguay')
                );
                self::render_input_field(
                    self::OPTION_INFO_REQUEST_WEBHOOK_URL,
                    __('Solicitud de información', 'flacso-uruguay'),
                    'url',
                    'https://tu-dominio.com/api/consultas',
                    __('Usado por el bloque “Solicitud de Información” de oferta académica.', 'flacso-uruguay')
                );
                ?>
            </div>

            <span class="flacso-integrations-note">🔒 <?php esc_html_e('Usa el Token Global superior', 'flacso-uruguay'); ?></span>
        </section>
        <?php
    }

    private static function render_charlas_card(): void {
        ?>
        <section class="flacso-integrations-card card-charlas">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">🎤</span>
                    <h2><?php esc_html_e('Charlas abiertas', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Configura el webhook que recibe inscripciones para la app. Comparte el mismo token global.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_input_field(
                    self::OPTION_CHARLAS_WEBHOOK_URL,
                    __('Webhook URL', 'flacso-uruguay'),
                    'url',
                    'https://tu-dominio.com/api/charlas/inscripciones',
                    __('Ruta sugerida si el destino es FLACSO Editor.', 'flacso-uruguay')
                );
                ?>
            </div>

            <span class="flacso-integrations-note">🔒 <?php esc_html_e('Usa el Token Global superior', 'flacso-uruguay'); ?></span>
        </section>
        <?php
    }

    private static function render_oferta_flotante_card(): void {
        ?>
        <section class="flacso-integrations-card card-flotante">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">⚡</span>
                    <h2><?php esc_html_e('Formulario flotante', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Este es el endpoint del botón flotante “Solicitar información”. No usa el mismo contrato que el bloque de solicitud de información.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_input_field(
                    self::OPTION_OFERTA_FLOTANTE_ENDPOINT,
                    __('Endpoint del formulario flotante', 'flacso-uruguay'),
                    'url',
                    'https://ejemplo.com/webhook/consultas',
                    __('Hoy este flujo solo usa URL; no envía token propio.', 'flacso-uruguay')
                );
                ?>
            </div>

            <span class="flacso-integrations-note"><?php esc_html_e('Flujo distinto al bloque “Solicitud de Información”', 'flacso-uruguay'); ?></span>
        </section>
        <?php
    }

    private static function render_preinscripciones_card(): void {
        ?>
        <section class="flacso-integrations-card card-preinscripciones">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">📝</span>
                    <h2><?php esc_html_e('Preinscripciones', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Define el webhook externo que recibe los datos de preinscripción. Suele apuntar a Google Apps Script.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_input_field(
                    self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL,
                    __('Webhook de preinscripciones', 'flacso-uruguay'),
                    'url',
                    'https://script.google.com/macros/s/.../exec',
                    __('Se guarda sobre la misma opción usada por el panel de Preinscripciones.', 'flacso-uruguay')
                );
                ?>
            </div>
        </section>
        <?php
    }

    private static function render_external_editor_card(): void {
        ?>
        <section class="flacso-integrations-card card-external-editor">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon" style="background: #e0f2fe; color: #0ea5e9;">✏️</span>
                    <h2><?php esc_html_e('Editor Externo (React)', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Configura la URL base del editor externo (React/Next.js) que se utilizará para crear y editar páginas de Oferta Académica.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_input_field(
                    self::OPTION_EXTERNAL_EDITOR_URL,
                    __('URL Base del Editor', 'flacso-uruguay'),
                    'url',
                    'https://editor-flacso-uy.vercel.app',
                    __('Las páginas reedirigirán a esta URL (ej: https://editor-flacso-uy.vercel.app/ofertas/ID).', 'flacso-uruguay')
                );
                ?>
            </div>
        </section>
        <?php
    }

    private static function render_mailjet_card(): void {
        ?>
        <section class="flacso-integrations-card card-mailjet">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">✉️</span>
                    <h2><?php esc_html_e('Mailjet Mailing', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Credenciales para el formulario de suscripción al mailing. Se usan para dar de alta contactos en una lista de Mailjet desde WordPress.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_input_field(
                    self::OPTION_MAILJET_API_KEY,
                    __('Mailjet API Key', 'flacso-uruguay'),
                    'text',
                    'xxxxxxxxxxxxxxxxxxxxxxxx',
                    __('Clave pública de la API de Mailjet.', 'flacso-uruguay')
                );
                self::render_input_field(
                    self::OPTION_MAILJET_SECRET_KEY,
                    __('Mailjet Secret Key', 'flacso-uruguay'),
                    'password',
                    'xxxxxxxxxxxxxxxxxxxxxxxx',
                    __('Clave privada asociada a la API Key anterior.', 'flacso-uruguay')
                );
                self::render_mailjet_list_field();
                ?>
            </div>

            <span class="flacso-integrations-note"><?php esc_html_e('Usado por el nuevo bloque de suscripción de la portada', 'flacso-uruguay'); ?></span>
        </section>
        <?php
    }

    private static function render_mailjet_list_field(): void {
        $value = trim((string) get_option(self::OPTION_MAILJET_LIST_ID, ''));
        $lists = self::get_mailjet_contact_lists();

        if (empty($lists)) {
            self::render_input_field(
                self::OPTION_MAILJET_LIST_ID,
                __('Mailjet Contact List ID', 'flacso-uruguay'),
                'text',
                '123456789',
                __('Guardá primero la API Key y la Secret Key para cargar listas automáticamente. Si lo preferís, podés pegar el ID manualmente.', 'flacso-uruguay')
            );

            return;
        }

        $selected_exists = false;
        foreach ($lists as $list) {
            if (($list['id'] ?? '') === $value) {
                $selected_exists = true;
                break;
            }
        }

        ?>
        <div class="flacso-integrations-field">
            <label for="<?php echo esc_attr(self::OPTION_MAILJET_LIST_ID); ?>"><?php esc_html_e('Mailjet Contact List', 'flacso-uruguay'); ?></label>
            <select
                id="<?php echo esc_attr(self::OPTION_MAILJET_LIST_ID); ?>"
                name="<?php echo esc_attr(self::OPTION_MAILJET_LIST_ID); ?>"
                class="regular-text code">
                <option value=""><?php esc_html_e('Seleccioná una lista de Mailjet', 'flacso-uruguay'); ?></option>
                <?php if ($value !== '' && !$selected_exists): ?>
                    <option value="<?php echo esc_attr($value); ?>" selected>
                        <?php
                        printf(
                            /* translators: %s: list ID */
                            esc_html__('Lista actual no encontrada (ID %s)', 'flacso-uruguay'),
                            esc_html($value)
                        );
                        ?>
                    </option>
                <?php endif; ?>
                <?php foreach ($lists as $list): ?>
                    <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($value, $list['id']); ?>>
                        <?php
                        printf(
                            '%1$s (ID %2$s, %3$d)',
                            esc_html($list['name']),
                            esc_html($list['id']),
                            absint($list['subscribers'])
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="flacso-integrations-help">
                <?php esc_html_e('La lista se carga dinámicamente desde Mailjet usando las credenciales guardadas. El último número indica la cantidad de contactos registrados en la lista.', 'flacso-uruguay'); ?>
            </p>
        </div>
        <?php
    }

    private static function render_services_card(): void {
        ?>
        <section class="flacso-integrations-card card-servicios">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">⚙️</span>
                    <h2><?php esc_html_e('Servicios auxiliares', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Claves asociadas a integraciones complementarias del formulario general.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_input_field(
                    self::OPTION_TELEGRAM_BOT_TOKEN,
                    __('Telegram Bot Token', 'flacso-uruguay'),
                    'password',
                    '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
                    __('Se usa si están activadas las notificaciones por Telegram en el formulario general.', 'flacso-uruguay')
                );
                self::render_input_field(
                    self::OPTION_TELEGRAM_CHAT_ID,
                    __('Telegram Chat ID', 'flacso-uruguay'),
                    'text',
                    '7456441753 o -1001234567890',
                    __('Chat o canal de destino para las notificaciones.', 'flacso-uruguay')
                );
                self::render_input_field(
                    self::OPTION_RECAPTCHA_SITE_KEY,
                    __('reCAPTCHA Site Key', 'flacso-uruguay'),
                    'text',
                    '6LeIxAcTAAAAA...',
                    __('Clave pública usada por el formulario general.', 'flacso-uruguay')
                );
                self::render_input_field(
                    self::OPTION_RECAPTCHA_SECRET_KEY,
                    __('reCAPTCHA Secret Key', 'flacso-uruguay'),
                    'password',
                    '6LeIxAcTAAAAA...',
                    __('Clave privada usada para validar reCAPTCHA en servidor.', 'flacso-uruguay')
                );
                ?>
            </div>
        </section>
        <?php
    }

    private static function render_input_field(string $option_name, string $label, string $type, string $placeholder, string $description): void {
        $value = get_option($option_name, '');
        $value = is_scalar($value) ? (string) $value : '';
        ?>
        <div class="flacso-integrations-field">
            <label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label>
            <input
                id="<?php echo esc_attr($option_name); ?>"
                name="<?php echo esc_attr($option_name); ?>"
                type="<?php echo esc_attr($type); ?>"
                class="regular-text code"
                value="<?php echo esc_attr($value); ?>"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                autocomplete="<?php echo 'password' === $type ? 'new-password' : 'off'; ?>"
                spellcheck="false"
            />
            <p class="flacso-integrations-help"><?php echo esc_html($description); ?></p>
        </div>
        <?php
    }

    private static function render_test_form(string $action, string $nonce_name, string $nonce_action, string $title, string $description): void {
        ?>
        <form class="flacso-integrations-test-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html($description); ?></p>
            <?php wp_nonce_field($nonce_action, $nonce_name); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>" />
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr(self::get_page_url()); ?>" />
            <?php submit_button(__('Ejecutar prueba', 'flacso-uruguay'), 'secondary', 'submit', false); ?>
            <div class="flacso-test-result-placeholder"></div>
        </form>
        <?php
    }

    private static function render_inline_notices(): void {
        if (!function_exists('fc_render_webhook_test_notice')) {
            return;
        }

        if (isset($_GET['fc_consultas_webhook_test'])) {
            $status = sanitize_key(wp_unslash($_GET['fc_consultas_webhook_test']));
            $code = isset($_GET['fc_consultas_webhook_code']) ? absint(wp_unslash($_GET['fc_consultas_webhook_code'])) : 0;
            $message = isset($_GET['fc_consultas_webhook_message']) ? sanitize_text_field(wp_unslash($_GET['fc_consultas_webhook_message'])) : '';
            fc_render_webhook_test_notice($status, $code, $message, 'FLACSO_WEBHOOK_TOKEN');
        }

        if (isset($_GET['fc_oferta_webhook_test'])) {
            $status = sanitize_key(wp_unslash($_GET['fc_oferta_webhook_test']));
            $code = isset($_GET['fc_oferta_webhook_code']) ? absint(wp_unslash($_GET['fc_oferta_webhook_code'])) : 0;
            $message = isset($_GET['fc_oferta_webhook_message']) ? sanitize_text_field(wp_unslash($_GET['fc_oferta_webhook_message'])) : '';
            fc_render_webhook_test_notice($status, $code, $message, 'FLACSO_WEBHOOK_TOKEN');
        }

        if (!isset($_GET['flacso_charlas_webhook_test'])) {
            return;
        }

        $status = sanitize_key(wp_unslash($_GET['flacso_charlas_webhook_test']));
        $code = isset($_GET['flacso_charlas_webhook_code']) ? absint(wp_unslash($_GET['flacso_charlas_webhook_code'])) : 0;
        $message = isset($_GET['flacso_charlas_webhook_message']) ? sanitize_text_field(wp_unslash($_GET['flacso_charlas_webhook_message'])) : '';

        fc_render_webhook_test_notice($status, $code, $message, 'FLACSO_WEBHOOK_TOKEN');
    }
}

FLACSO_Integrations_Settings::init();
