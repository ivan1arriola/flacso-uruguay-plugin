<?php

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Integrations_Settings {
    private const PAGE_SLUG = 'flacso-integraciones';
    private const PAGE_SLUG_META = 'flacso-integracion-meta';
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
    private const OPTION_NAV_ANNOUNCEMENT_ENABLED = 'flacso_nav_announcement_enabled';
    private const OPTION_NAV_ANNOUNCEMENT_URL = 'flacso_nav_announcement_url';
    private const OPTION_NAV_ANNOUNCEMENT_KICKER = 'flacso_nav_announcement_kicker';
    private const OPTION_NAV_ANNOUNCEMENT_MESSAGE = 'flacso_nav_announcement_message';
    private const OPTION_NAV_ANNOUNCEMENT_CTA = 'flacso_nav_announcement_cta';
    private const OPTION_NAV_ANNOUNCEMENT_ARIA = 'flacso_nav_announcement_aria';
    private const OPTION_NAV_ANNOUNCEMENT_HIDE_FORMACION = 'flacso_nav_announcement_hide_formacion';
    private const OPTION_MAILJET_API_KEY = 'flacso_mailjet_api_key';
    private const OPTION_MAILJET_SECRET_KEY = 'flacso_mailjet_secret_key';
    private const OPTION_MAILJET_LIST_ID = 'flacso_mailjet_list_id';
    private const OPTION_MAILJET_SENDER_EMAIL = 'flacso_mailjet_sender_email';
    private const OPTION_MAILJET_SENDER_NAME = 'flacso_mailjet_sender_name';
    private const OPTION_CARTA_CTA_TITULO_DEFAULT = 'flacso_carta_cta_titulo_default';
    private const OPTION_META_ENABLED = 'flacso_meta_enabled';
    private const OPTION_META_PIXEL_ID = 'flacso_meta_pixel_id';
    private const OPTION_META_ACCESS_TOKEN = 'flacso_meta_access_token';
    private const OPTION_META_TEST_EVENT_CODE = 'flacso_meta_test_event_code';
    private const OPTION_META_TRACK_PAGEVIEW = 'flacso_meta_track_pageview';
    private const OPTION_META_LAST_TEST_RESULT = 'flacso_meta_last_test_result';
    private const OPTION_META_LEADS_ENABLED = 'flacso_meta_leads_enabled';
    private const OPTION_META_LEADS_VERIFY_TOKEN = 'flacso_meta_leads_verify_token';
    private const OPTION_META_LEADS_PAGE_ACCESS_TOKEN = 'flacso_meta_leads_page_access_token';
    private const OPTION_META_LEADS_APP_SECRET = 'flacso_meta_leads_app_secret';
    private const OPTION_META_LEADS_PAGE_ID = 'flacso_meta_leads_page_id';
    private const OPTION_META_LEADS_FORM_IDS = 'flacso_meta_leads_form_ids';
    private const OPTION_META_LEADS_OFFER_FIELD = 'flacso_meta_leads_offer_field';
    private const OPTION_META_LEADS_GRAPH_VERSION = 'flacso_meta_leads_graph_version';
    private const OPTION_META_LEADS_FORWARD_TO_WEBHOOK = 'flacso_meta_leads_forward_to_webhook';
    private const OPTION_META_LEADS_LAST_PERMISSION_CHECK = 'flacso_meta_leads_last_permission_check';
    private const OPTION_USD_EXCHANGE_RATE = 'flacso_usd_exchange_rate';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            FLACSO_Admin_Panel::PAGE_SLUG,
            __('Integraciones FLACSO', 'flacso-uruguay'),
            __('Integraciones FLACSO', 'flacso-uruguay'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );

        add_submenu_page(
            FLACSO_Admin_Panel::PAGE_SLUG,
            __('Integración con Meta', 'flacso-uruguay'),
            __('Integración con Meta', 'flacso-uruguay'),
            'manage_options',
            self::PAGE_SLUG_META,
            [self::class, 'render_meta_page']
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
            self::OPTION_NAV_ANNOUNCEMENT_ENABLED,
            [
                'type' => 'boolean',
                'sanitize_callback' => [self::class, 'sanitize_checkbox'],
                'default' => 0,
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAV_ANNOUNCEMENT_URL,
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAV_ANNOUNCEMENT_KICKER,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'Próxima apertura',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAV_ANNOUNCEMENT_MESSAGE,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'Diplomas 2026 · Segundo semestre',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAV_ANNOUNCEMENT_CTA,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'Postúlate ahora',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAV_ANNOUNCEMENT_ARIA,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAV_ANNOUNCEMENT_HIDE_FORMACION,
            [
                'type' => 'boolean',
                'sanitize_callback' => [self::class, 'sanitize_checkbox'],
                'default' => 1,
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

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_MAILJET_SENDER_EMAIL,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default' => get_option('admin_email'),
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_MAILJET_SENDER_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_CARTA_CTA_TITULO_DEFAULT,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'Comenzá el año cursando un posgrado en FLACSO Uruguay',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_ENABLED,
            [
                'type' => 'boolean',
                'sanitize_callback' => [self::class, 'sanitize_checkbox'],
                'default' => 0,
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_PIXEL_ID,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_meta_pixel_id'],
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_ACCESS_TOKEN,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_TEST_EVENT_CODE,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_TRACK_PAGEVIEW,
            [
                'type' => 'boolean',
                'sanitize_callback' => [self::class, 'sanitize_checkbox'],
                'default' => 1,
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_LEADS_ENABLED,
            [
                'type' => 'boolean',
                'sanitize_callback' => [self::class, 'sanitize_checkbox'],
                'default' => 0,
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_LEADS_VERIFY_TOKEN,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_secret_text'],
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_LEADS_PAGE_ACCESS_TOKEN,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_secret_text'],
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_LEADS_APP_SECRET,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_secret_text'],
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_LEADS_PAGE_ID,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_meta_numeric_id'],
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_LEADS_FORM_IDS,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_meta_leads_form_ids'],
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_LEADS_OFFER_FIELD,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_key',
                'default' => 'programa',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_LEADS_GRAPH_VERSION,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_meta_graph_version'],
                'default' => 'v25.0',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_META_LEADS_FORWARD_TO_WEBHOOK,
            [
                'type' => 'boolean',
                'sanitize_callback' => [self::class, 'sanitize_checkbox'],
                'default' => 1,
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_USD_EXCHANGE_RATE,
            [
                'type' => 'number',
                'sanitize_callback' => 'absint',
                'default' => 40,
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

    public static function sanitize_checkbox($value): int {
        return !empty($value) ? 1 : 0;
    }

    public static function sanitize_meta_pixel_id($value): string {
        return preg_replace('/[^0-9]/', '', (string) $value) ?: '';
    }

    public static function sanitize_meta_numeric_id($value): string {
        return preg_replace('/[^0-9]/', '', (string) $value) ?: '';
    }

    public static function sanitize_secret_text($value): string {
        return trim(sanitize_text_field((string) $value));
    }

    public static function sanitize_meta_graph_version($value): string {
        $value = trim((string) $value);

        return preg_match('/^v[0-9]+\.[0-9]+$/', $value) ? $value : 'v25.0';
    }

    public static function sanitize_meta_leads_form_ids($value): string {
        $parts = preg_split('/[\s,;]+/', (string) $value) ?: [];
        $ids = [];

        foreach ($parts as $part) {
            $id = self::sanitize_meta_numeric_id($part);
            if ($id !== '') {
                $ids[$id] = $id;
            }
        }

        return implode("\n", array_values($ids));
    }

    public static function get_mailjet_settings(): array {
        return [
            'api_key' => trim((string) get_option(self::OPTION_MAILJET_API_KEY, '')),
            'secret_key' => trim((string) get_option(self::OPTION_MAILJET_SECRET_KEY, '')),
            'list_id' => trim((string) get_option(self::OPTION_MAILJET_LIST_ID, '')),
            'sender_email' => sanitize_email((string) get_option(self::OPTION_MAILJET_SENDER_EMAIL, get_option('admin_email'))),
            'sender_name' => trim((string) get_option(self::OPTION_MAILJET_SENDER_NAME, wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES))),
        ];
    }

    public static function get_meta_settings(): array {
        $pixel_id = trim((string) get_option(self::OPTION_META_PIXEL_ID, ''));
        $access_token = trim((string) get_option(self::OPTION_META_ACCESS_TOKEN, ''));

        return [
            'enabled' => (bool) get_option(self::OPTION_META_ENABLED, 0),
            'pixel_id' => $pixel_id,
            'access_token' => $access_token,
            'test_event_code' => trim((string) get_option(self::OPTION_META_TEST_EVENT_CODE, '')),
            'track_pageview' => (bool) get_option(self::OPTION_META_TRACK_PAGEVIEW, 1),
            'capi_enabled' => $access_token !== '',
            'is_ready' => $pixel_id !== '',
            'last_test' => self::get_meta_last_test_result(),
        ];
    }

    public static function get_meta_leads_settings(): array {
        $form_ids = self::sanitize_meta_leads_form_ids(get_option(self::OPTION_META_LEADS_FORM_IDS, ''));
        $form_id_list = $form_ids !== '' ? explode("\n", $form_ids) : [];

        return [
            'enabled' => (bool) get_option(self::OPTION_META_LEADS_ENABLED, 0),
            'verify_token' => trim((string) get_option(self::OPTION_META_LEADS_VERIFY_TOKEN, '')),
            'page_access_token' => trim((string) get_option(self::OPTION_META_LEADS_PAGE_ACCESS_TOKEN, '')),
            'app_secret' => trim((string) get_option(self::OPTION_META_LEADS_APP_SECRET, '')),
            'page_id' => self::sanitize_meta_numeric_id(get_option(self::OPTION_META_LEADS_PAGE_ID, '')),
            'form_ids_raw' => $form_ids,
            'form_ids' => array_values(array_filter($form_id_list)),
            'offer_field' => sanitize_key((string) get_option(self::OPTION_META_LEADS_OFFER_FIELD, 'programa')) ?: 'programa',
            'graph_version' => self::sanitize_meta_graph_version(get_option(self::OPTION_META_LEADS_GRAPH_VERSION, 'v25.0')),
            'forward_to_webhook' => (bool) get_option(self::OPTION_META_LEADS_FORWARD_TO_WEBHOOK, 1),
            'endpoint_url' => self::get_meta_leads_endpoint_url(),
            'last_permission_check' => self::get_meta_leads_last_permission_check(),
        ];
    }

    public static function get_meta_leads_endpoint_url(): string {
        return rest_url('flacso/v1/meta-leads');
    }

    public static function get_meta_leads_last_permission_check(): array {
        $value = get_option(self::OPTION_META_LEADS_LAST_PERMISSION_CHECK, []);

        return is_array($value) ? $value : [];
    }

    public static function store_meta_leads_last_permission_check(array $result): void {
        update_option(self::OPTION_META_LEADS_LAST_PERMISSION_CHECK, $result, false);
    }

    public static function get_meta_last_test_result(): array {
        $value = get_option(self::OPTION_META_LAST_TEST_RESULT, []);

        return is_array($value) ? $value : [];
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

        $cache_key = 'flacso_mailjet_lists_v2_' . md5($settings['api_key'] . '|' . $settings['secret_key']);
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $auth_header = 'Basic ' . base64_encode($settings['api_key'] . ':' . $settings['secret_key']);
        $limit = 1000;
        $offset = 0;
        $items = [];

        do {
            $response = wp_remote_get(
                add_query_arg(
                    [
                        'Limit' => $limit,
                        'Offset' => $offset,
                    ],
                    'https://api.mailjet.com/v3/REST/contactslist'
                ),
                [
                    'timeout' => 15,
                    'headers' => [
                        'Authorization' => $auth_header,
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
            $page_items = isset($decoded['Data']) && is_array($decoded['Data']) ? $decoded['Data'] : [];
            $items = array_merge($items, $page_items);

            $page_count = count($page_items);
            $total = isset($decoded['Total']) ? absint($decoded['Total']) : 0;
            $offset += $page_count;
        } while ($page_count === $limit || ($total > 0 && $offset < $total));

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

    public static function get_meta_page_url(array $args = []): string {
        return add_query_arg(
            array_merge(
                [
                    'page' => self::PAGE_SLUG_META,
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
                <?php self::render_migration_banner(); ?>
                <?php self::render_inline_notices(); ?>


                <form method="post" action="options.php">
                    <?php settings_fields(self::SETTINGS_GROUP); ?>

                    <!-- Token de Acceso Global Único -->
                    <?php self::render_global_token_card(); ?>

                    <div class="flacso-integrations-grid">
                        <?php self::render_consultas_card(); ?>
                        <?php self::render_ofertas_card(); ?>
                        <?php self::render_charlas_card(); ?>
                        <?php self::render_oferta_flotante_card(); ?>
                        <?php self::render_preinscripciones_card(); ?>
                        <?php self::render_external_editor_card(); ?>
                        <?php self::render_nav_announcement_card(); ?>
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
                        <?php self::render_test_form(
                            'flacso_preinscripciones_test_webhook',
                            'flacso_preinscripciones_test_webhook_nonce',
                            'flacso_preinscripciones_test_webhook',
                            __('Probar preinscripciones', 'flacso-uruguay'),
                            __('Valida el webhook de preinscripciones académicas.', 'flacso-uruguay')
                        ); ?>
                    </div>
                </div>

                <div class="flacso-integrations-links">
                    <h2>🔗 <?php esc_html_e('Accesos Directos Relacionados', 'flacso-uruguay'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=flacso-panel')); ?>">← <?php esc_html_e('Volver al panel FLACSO', 'flacso-uruguay'); ?></a></li>
                        <li><a href="<?php echo esc_url(self::get_meta_page_url()); ?>">📈 <?php esc_html_e('Integración con Meta', 'flacso-uruguay'); ?></a></li>
                        <li><a href="https://preinscripciones.flacso.edu.uy" target="_blank" rel="noopener noreferrer">📝 <?php esc_html_e('Abrir preinscripciones externas', 'flacso-uruguay'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php self::render_admin_styles(); ?>
        <?php self::render_test_script(); ?>
        <?php
    }

    public static function render_meta_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <div class="flacso-integrations-dashboard flacso-integrations-dashboard--narrow">
                <header class="flacso-dashboard-header">
                    <div class="flacso-dashboard-title-area">
                        <h1 class="flacso-dashboard-title">
                            <?php esc_html_e('Integración con Meta', 'flacso-uruguay'); ?>
                            <span class="flacso-badge"><?php esc_html_e('Pixel + CAPI', 'flacso-uruguay'); ?></span>
                        </h1>
                    </div>
                    <p class="flacso-dashboard-subtitle">
                        <?php esc_html_e('Pantalla dedicada para administrar, verificar y diagnosticar la integración nativa con Meta desde el plugin, sin depender del plugin externo.', 'flacso-uruguay'); ?>
                    </p>
                </header>

                <?php settings_errors(); ?>
                <?php self::render_migration_banner(); ?>
                <?php self::render_inline_notices(); ?>


                <form method="post" action="options.php">
                    <?php settings_fields(self::SETTINGS_GROUP); ?>

                    <div class="flacso-integrations-grid flacso-integrations-grid--single">
                        <?php self::render_meta_card(false); ?>
                    </div>

                    <div class="flacso-submit-section">
                        <?php submit_button(__('Guardar configuración de Meta', 'flacso-uruguay')); ?>
                    </div>
                </form>
                <?php self::render_meta_leads_permission_form(self::get_meta_page_url()); ?>

                <div class="flacso-integrations-grid flacso-integrations-grid--single">
                    <?php self::render_meta_test_panel(self::get_meta_page_url()); ?>
                </div>

                <div class="flacso-integrations-links">
                    <h2>🔗 <?php esc_html_e('Accesos Relacionados', 'flacso-uruguay'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(self::get_page_url()); ?>">↩ <?php esc_html_e('Volver a Integraciones FLACSO', 'flacso-uruguay'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php self::render_admin_styles(); ?>
        <?php
    }

    private static function render_admin_styles(): void {
        ?>
        <style>
            .flacso-integrations-dashboard {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                max-width: 1280px;
                margin: 20px auto 40px;
                padding: 0 10px;
            }

            .flacso-integrations-dashboard--narrow {
                max-width: 920px;
            }

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

            .flacso-integrations-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
                gap: 24px;
                margin-bottom: 24px;
            }

            .flacso-integrations-grid--single {
                grid-template-columns: minmax(0, 1fr);
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

            .card-consultas .flacso-card-icon { background: #eff6ff; color: #3b82f6; }
            .card-ofertas .flacso-card-icon { background: #eef2ff; color: #4338ca; }
            .card-meta .flacso-card-icon { background: #effcf6; color: #059669; }
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

            .flacso-integrations-field {
                margin-bottom: 18px;
            }

            .flacso-integrations-field label:not(.flacso-toggle-control),
            .flacso-field-label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: #334155;
                margin-bottom: 6px;
            }

            .flacso-integrations-field input:not([type="checkbox"]):not([type="hidden"]),
            .flacso-integrations-field textarea,
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

            .flacso-integrations-field input:not([type="checkbox"]):not([type="hidden"]):focus,
            .flacso-integrations-field textarea:focus,
            .flacso-integrations-field select:focus {
                border-color: #3b82f6;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
                outline: none;
            }

            .flacso-integrations-field textarea {
                min-height: 96px;
                resize: vertical;
                font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace;
            }

            .flacso-integrations-field input:not([type="checkbox"]):not([type="hidden"])[disabled] {
                background: #e2e8f0;
                border-color: #cbd5e1;
                color: #475569;
                cursor: not-allowed;
                font-weight: 550;
            }

            .flacso-integrations-field--checkbox {
                border: 1px solid #dbe7f3;
                border-radius: 14px;
                padding: 16px;
                background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            }

            .flacso-toggle-control {
                display: inline-flex;
                align-items: center;
                gap: 12px;
                margin: 0;
                cursor: pointer;
                user-select: none;
            }

            .flacso-toggle-control__input {
                position: absolute;
                opacity: 0;
                width: 1px;
                height: 1px;
                pointer-events: none;
            }

            .flacso-toggle-control__switch {
                position: relative;
                width: 56px;
                height: 32px;
                border-radius: 999px;
                background: #cbd5e1;
                transition: background 0.2s ease, box-shadow 0.2s ease;
                box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
                flex-shrink: 0;
            }

            .flacso-toggle-control__switch::after {
                content: '';
                position: absolute;
                top: 4px;
                left: 4px;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: #ffffff;
                box-shadow: 0 2px 6px rgba(15, 23, 42, 0.25);
                transition: transform 0.2s ease;
            }

            .flacso-toggle-control__input:checked + .flacso-toggle-control__switch {
                background: #2563eb;
            }

            .flacso-toggle-control__input:checked + .flacso-toggle-control__switch::after {
                transform: translateX(24px);
            }

            .flacso-toggle-control__input:focus-visible + .flacso-toggle-control__switch {
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            }

            .flacso-toggle-control__labels {
                display: inline-flex;
                align-items: center;
                min-width: 96px;
                font-size: 13px;
                font-weight: 700;
            }

            .flacso-toggle-control__state--on {
                display: none;
                color: #1d4ed8;
            }

            .flacso-toggle-control__state--off {
                color: #64748b;
            }

            .flacso-toggle-control__input:checked + .flacso-toggle-control__switch + .flacso-toggle-control__labels .flacso-toggle-control__state--on {
                display: inline;
            }

            .flacso-toggle-control__input:checked + .flacso-toggle-control__switch + .flacso-toggle-control__labels .flacso-toggle-control__state--off {
                display: none;
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

            .flacso-meta-status-panel {
                background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
                border: 1px solid #dbe7f3;
                border-radius: 14px;
                padding: 18px;
                margin-bottom: 18px;
            }

            .flacso-meta-status-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 14px;
            }

            .flacso-meta-status-head h3 {
                margin: 0 0 4px;
                font-size: 15px;
                font-weight: 800;
                color: #0f172a;
            }

            .flacso-meta-status-head p {
                margin: 0;
                font-size: 12.5px;
                color: #64748b;
                line-height: 1.5;
            }

            .flacso-meta-status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                border-radius: 999px;
                padding: 6px 10px;
                font-size: 11px;
                font-weight: 800;
                white-space: nowrap;
                border: 1px solid transparent;
            }

            .flacso-meta-status-badge--success {
                background: #ecfdf5;
                color: #047857;
                border-color: #a7f3d0;
            }

            .flacso-meta-status-badge--warning {
                background: #fff7ed;
                color: #c2410c;
                border-color: #fdba74;
            }

            .flacso-meta-status-badge--danger {
                background: #fef2f2;
                color: #b91c1c;
                border-color: #fecaca;
            }

            .flacso-meta-status-badge--neutral {
                background: #f1f5f9;
                color: #475569;
                border-color: #cbd5e1;
            }

            .flacso-meta-checklist {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .flacso-meta-check {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 12px 14px;
                background: #ffffff;
            }

            .flacso-meta-check strong {
                display: block;
                margin-bottom: 3px;
                color: #0f172a;
            }

            .flacso-meta-check p {
                margin: 0;
                font-size: 12px;
                color: #64748b;
                line-height: 1.45;
            }

            .flacso-meta-check .flacso-meta-status-badge {
                flex-shrink: 0;
            }

            .flacso-meta-test-box {
                border: 1px dashed #cbd5e1;
                border-radius: 12px;
                padding: 16px;
                background: #f8fafc;
                margin-top: 2px;
            }

            .flacso-meta-test-box h3 {
                margin: 0 0 6px;
                font-size: 15px;
                font-weight: 800;
                color: #0f172a;
            }

            .flacso-meta-test-box p {
                margin: 0 0 12px;
                font-size: 12.5px;
                color: #64748b;
                line-height: 1.5;
            }

            .flacso-meta-test-box .button-secondary[disabled] {
                cursor: not-allowed;
                opacity: .6;
            }

            .flacso-meta-last-test {
                margin-top: 12px;
                border-top: 1px solid #e2e8f0;
                padding-top: 12px;
            }

            .flacso-meta-last-test h4 {
                margin: 0 0 8px;
                font-size: 13px;
                font-weight: 800;
                color: #0f172a;
            }

            .flacso-meta-last-test p {
                margin: 0 0 8px;
                font-size: 12px;
                color: #475569;
                line-height: 1.5;
            }

            .flacso-meta-last-test ul {
                margin: 0;
                padding-left: 18px;
                color: #475569;
                font-size: 12px;
            }

            .flacso-meta-last-test li {
                margin: 3px 0;
            }

            .flacso-meta-leads-endpoint {
                display: flex;
                gap: 10px;
                align-items: center;
            }

            .flacso-meta-leads-endpoint code {
                display: block;
                flex: 1;
                padding: 10px 12px;
                border-radius: 8px;
                background: #e2e8f0;
                color: #0f172a;
                overflow-wrap: anywhere;
            }

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
        <?php
    }

    private static function render_test_script(): void {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const forms = document.querySelectorAll('.flacso-integrations-test-card');
                forms.forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();

                        const button = form.querySelector('input[type="submit"]');
                        const placeholder = form.querySelector('.flacso-test-result-placeholder');
                        const originalButtonVal = button.value;

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
                            } else if (finalUrl.searchParams.has('flacso_preinscripciones_webhook_test')) {
                                status = finalUrl.searchParams.get('flacso_preinscripciones_webhook_test');
                                code = finalUrl.searchParams.get('flacso_preinscripciones_webhook_code') || '';
                                message = finalUrl.searchParams.get('flacso_preinscripciones_webhook_message') || '';
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

    private static function render_ofertas_card(): void {
        ?>
        <section class="flacso-integrations-card card-ofertas">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">🎓</span>
                    <h2><?php esc_html_e('Ofertas académicas', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Ajustes globales compartidos por todas las cartas y páginas de oferta académica.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_input_field(
                    self::OPTION_CARTA_CTA_TITULO_DEFAULT,
                    __('Título CTA final de la carta', 'flacso-uruguay'),
                    'text',
                    'Comenzá el año cursando un posgrado en FLACSO Uruguay',
                    __('Se usa en el bloque final de la carta/preinscripción para todas las ofertas académicas.', 'flacso-uruguay')
                );
                ?>
            </div>
        </section>
        <?php
    }

    private static function render_meta_card(bool $include_test_box = true, string $redirect_url = ''): void {
        $meta = self::get_meta_settings();
        $redirect_url = $redirect_url !== '' ? $redirect_url : self::get_meta_page_url();
        ?>
        <section class="flacso-integrations-card card-meta">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">📈</span>
                    <h2><?php esc_html_e('Meta Pixel + CAPI', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Implementación nativa del plugin para reemplazar el plugin externo de Meta. El navegador dispara Pixel y WordPress envía los mismos eventos por Conversions API con deduplicación por eventID.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php self::render_meta_status_panel($meta); ?>
                <?php
                self::render_checkbox_field(
                    self::OPTION_META_ENABLED,
                    __('Activar tracking de Meta', 'flacso-uruguay'),
                    __('Si se activa, el plugin inyecta el Pixel y toma control del envío a CAPI.', 'flacso-uruguay')
                );
                self::render_input_field(
                    self::OPTION_META_PIXEL_ID,
                    __('Meta Pixel ID', 'flacso-uruguay'),
                    'text',
                    '123456789012345',
                    __('ID numérico del Pixel/dataset web que recibirá los eventos del sitio.', 'flacso-uruguay')
                );
                self::render_input_field(
                    self::OPTION_META_ACCESS_TOKEN,
                    __('Meta CAPI Access Token', 'flacso-uruguay'),
                    'password',
                    'EAAG...',
                    __('Token usado por WordPress para enviar eventos server-side al endpoint de Conversions API.', 'flacso-uruguay')
                );
                self::render_input_field(
                    self::OPTION_META_TEST_EVENT_CODE,
                    __('Meta Test Event Code', 'flacso-uruguay'),
                    'text',
                    'TEST12345',
                    __('Opcional. Si lo completás, CAPI enviará los eventos al modo de prueba de Meta Events Manager.', 'flacso-uruguay')
                );
                self::render_checkbox_field(
                    self::OPTION_META_TRACK_PAGEVIEW,
                    __('Enviar PageView automático', 'flacso-uruguay'),
                    __('Mantiene un PageView global desde el plugin, además de los eventos específicos como ViewContent, Lead y SubmitApplication.', 'flacso-uruguay')
                );
                ?>
                <div class="notice notice-info inline" style="margin:16px 0 0;padding:12px 14px;">
                    <p style="margin:0 0 6px;"><strong><?php esc_html_e('Coincidencias avanzadas', 'flacso-uruguay'); ?></strong></p>
                    <p style="margin:0;">
                        <?php esc_html_e('El plugin envía al Pixel y a CAPI únicamente los datos que la persona proporciona en los formularios (por ejemplo correo, teléfono y nombre). Meta los normaliza y protege con SHA-256. Para habilitar también la modalidad automática, actívala en Events Manager → Configuración → Coincidencias avanzadas automáticas.', 'flacso-uruguay'); ?>
                    </p>
                </div>
                <?php if ($include_test_box) : ?>
                    <?php self::render_meta_test_box($meta, $redirect_url); ?>
                <?php endif; ?>
                <?php self::render_meta_leads_box($redirect_url); ?>
            </div>

            <span class="flacso-integrations-note">
                <?php
                echo $meta['capi_enabled']
                    ? esc_html__('Cuando desactives el plugin externo de Meta, esta configuración seguirá cubriendo Pixel + CAPI desde WordPress.', 'flacso-uruguay')
                    : esc_html__('CAPI queda activo en cuanto guardes un Access Token válido. Mientras tanto, el plugin puede seguir emitiendo solo Pixel.', 'flacso-uruguay');
                ?>
            </span>
        </section>
        <?php
    }

    private static function render_meta_test_panel(string $redirect_url = ''): void {
        $meta = self::get_meta_settings();
        $redirect_url = $redirect_url !== '' ? $redirect_url : self::get_meta_page_url();
        ?>
        <section class="flacso-integrations-card card-meta">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">🧪</span>
                    <h2><?php esc_html_e('Pruebas y diagnóstico', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Validá la conexión con Meta por Conversions API sin interferir con el guardado de la configuración.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php self::render_meta_test_box($meta, $redirect_url); ?>
            </div>
        </section>
        <?php
    }

    private static function render_meta_status_panel(array $meta): void {
        $snapshot = self::get_meta_status_snapshot($meta);
        ?>
        <div class="flacso-meta-status-panel">
            <div class="flacso-meta-status-head">
                <div>
                    <h3><?php esc_html_e('Estado actual', 'flacso-uruguay'); ?></h3>
                    <p><?php echo esc_html($snapshot['description']); ?></p>
                </div>
                <?php self::render_meta_status_badge($snapshot['variant'], $snapshot['label']); ?>
            </div>
            <div class="flacso-meta-checklist">
                <?php foreach ($snapshot['checks'] as $check): ?>
                    <div class="flacso-meta-check">
                        <div>
                            <strong><?php echo esc_html($check['label']); ?></strong>
                            <p><?php echo esc_html($check['description']); ?></p>
                        </div>
                        <?php self::render_meta_status_badge($check['variant'], $check['status']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function render_meta_test_box(array $meta, string $redirect_url): void {
        $can_test = !empty($meta['enabled']) && !empty($meta['pixel_id']) && !empty($meta['access_token']) && !empty($meta['test_event_code']);
        $help = $can_test
            ? __('Envía un evento de prueba por CAPI a Meta usando el Test Event Code configurado. No genera un evento real de producción.', 'flacso-uruguay')
            : __('Para habilitar esta prueba necesitás: tracking activado, Pixel ID, Access Token y Test Event Code.', 'flacso-uruguay');
        ?>
        <div class="flacso-meta-test-box">
            <h3><?php esc_html_e('Prueba rápida de CAPI', 'flacso-uruguay'); ?></h3>
            <p><?php echo esc_html($help); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('flacso_meta_test_event', 'flacso_meta_test_event_nonce'); ?>
                <input type="hidden" name="action" value="flacso_meta_test_event" />
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_url); ?>" />
                <?php submit_button(__('Probar CAPI ahora', 'flacso-uruguay'), 'secondary', 'submit', false, $can_test ? [] : ['disabled' => 'disabled']); ?>
            </form>
            <?php self::render_meta_last_test_result($meta['last_test'] ?? []); ?>
        </div>
        <?php
    }

    private static function render_meta_leads_box(string $redirect_url): void {
        $leads = self::get_meta_leads_settings();
        $can_check = $leads['page_access_token'] !== '';
        ?>
        <div class="flacso-meta-test-box">
            <h3><?php esc_html_e('Meta Lead Ads', 'flacso-uruguay'); ?></h3>
            <p><?php esc_html_e('Recibe formularios instantáneos de Meta, descarga el lead original desde Graph API, lo reenvía al flujo de solicitud de información y sube el evento CRM inicial a Conversions API para Conversion Leads.', 'flacso-uruguay'); ?></p>
            <p class="flacso-integrations-note"><?php esc_html_e('El evento CRM usa el lead_id de Meta, event_source=crm, action_source=system_generated y event_name=initial_lead.', 'flacso-uruguay'); ?></p>

            <div class="flacso-integrations-field">
                <label><?php esc_html_e('URL del webhook para Meta', 'flacso-uruguay'); ?></label>
                <div class="flacso-meta-leads-endpoint">
                    <code><?php echo esc_html($leads['endpoint_url']); ?></code>
                </div>
                <p class="flacso-integrations-help"><?php esc_html_e('Usá esta URL al configurar el webhook leadgen en la app de Meta.', 'flacso-uruguay'); ?></p>
            </div>

            <?php
            self::render_checkbox_field(
                self::OPTION_META_LEADS_ENABLED,
                __('Activar recepción de Lead Ads', 'flacso-uruguay'),
                __('Si está apagado, el endpoint sigue respondiendo la verificación de Meta pero no procesa leads entrantes.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_META_LEADS_VERIFY_TOKEN,
                __('Verify Token del webhook', 'flacso-uruguay'),
                'password',
                'token-secreto-para-meta',
                __('Debe coincidir exactamente con el token configurado en Webhooks dentro de Meta Developers.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_META_LEADS_PAGE_ACCESS_TOKEN,
                __('Page Access Token para Lead Ads', 'flacso-uruguay'),
                'password',
                'EAAG...',
                __('Token de la página con permisos leads_retrieval y pages_manage_metadata para leer formularios y leads.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_META_LEADS_APP_SECRET,
                __('App Secret', 'flacso-uruguay'),
                'password',
                'app-secret',
                __('Opcional pero recomendado. Permite validar la firma X-Hub-Signature-256 de Meta en cada webhook.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_META_LEADS_PAGE_ID,
                __('Page ID de Facebook', 'flacso-uruguay'),
                'text',
                '123456789012345',
                __('ID de la página que contiene los formularios instantáneos.', 'flacso-uruguay')
            );
            self::render_textarea_field(
                self::OPTION_META_LEADS_FORM_IDS,
                __('Form IDs permitidos', 'flacso-uruguay'),
                "123456789012345\n987654321098765",
                __('Uno por línea. Si queda vacío, se aceptan todos los formularios que lleguen al webhook.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_META_LEADS_OFFER_FIELD,
                __('Campo de oferta/programa en Meta', 'flacso-uruguay'),
                'text',
                'programa',
                __('Nombre interno de la pregunta de Meta que identifica la oferta académica, por ejemplo programa u oferta.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_META_LEADS_GRAPH_VERSION,
                __('Versión de Graph API', 'flacso-uruguay'),
                'text',
                'v25.0',
                __('Versión usada para leer leads, formularios y suscripciones.', 'flacso-uruguay')
            );
            self::render_checkbox_field(
                self::OPTION_META_LEADS_FORWARD_TO_WEBHOOK,
                __('Reenviar al webhook de solicitud de información', 'flacso-uruguay'),
                __('Cuando esté activo, cada lead válido se envía al mismo endpoint configurado para solicitudes de información de oferta académica.', 'flacso-uruguay')
            );
            ?>

            <?php
            $button_attrs = ['form' => 'flacso-meta-leads-permissions-form'];
            if (!$can_check) {
                $button_attrs['disabled'] = 'disabled';
            }
            submit_button(__('Chequear permisos', 'flacso-uruguay'), 'secondary', 'submit', false, $button_attrs);
            ?>
            <?php self::render_meta_leads_permission_result($leads['last_permission_check']); ?>
        </div>
        <?php
    }

    private static function render_meta_leads_permission_form(string $redirect_url): void {
        ?>
        <form id="flacso-meta-leads-permissions-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
            <?php wp_nonce_field('flacso_meta_leads_check_permissions', 'flacso_meta_leads_check_permissions_nonce'); ?>
            <input type="hidden" name="action" value="flacso_meta_leads_check_permissions" />
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_url); ?>" />
        </form>
        <?php
    }

    private static function get_meta_status_snapshot(array $meta): array {
        $checks = [];

        $pixel_ready = !empty($meta['enabled']) && !empty($meta['pixel_id']);
        $capi_ready = !empty($meta['enabled']) && !empty($meta['access_token']);
        $test_ready = !empty($meta['test_event_code']);
        $pageview_ready = !empty($meta['enabled']) && !empty($meta['track_pageview']);

        $last_test = isset($meta['last_test']) && is_array($meta['last_test']) ? $meta['last_test'] : [];
        $last_test_success = !empty($last_test['status']) && $last_test['status'] === 'success';

        if (empty($meta['enabled'])) {
            $variant = 'neutral';
            $label = __('Desactivado', 'flacso-uruguay');
            $description = __('El plugin no está enviando eventos de Meta porque el tracking global está apagado.', 'flacso-uruguay');
        } elseif (empty($meta['pixel_id'])) {
            $variant = 'danger';
            $label = __('Incompleto', 'flacso-uruguay');
            $description = __('Falta el Pixel ID. Sin ese dato no se puede inicializar el Pixel del navegador ni completar la configuración.', 'flacso-uruguay');
        } elseif (empty($meta['access_token'])) {
            $variant = 'warning';
            $label = __('Solo Pixel', 'flacso-uruguay');
            $description = __('El frontend puede cargar el Pixel, pero CAPI todavía no está listo porque falta el Access Token.', 'flacso-uruguay');
        } elseif ($last_test_success) {
            $variant = 'success';
            $label = __('Verificado', 'flacso-uruguay');
            $description = __('La última prueba desde WordPress fue aceptada por Meta. Podés revisar abajo la fecha, el fbtrace_id y la cantidad de eventos recibidos.', 'flacso-uruguay');
        } elseif (!$test_ready) {
            $variant = 'warning';
            $label = __('Listo sin prueba', 'flacso-uruguay');
            $description = __('La configuración principal está completa. Si agregás un Test Event Code, además vas a poder validar CAPI desde esta pantalla.', 'flacso-uruguay');
        } else {
            $variant = 'success';
            $label = __('Listo para probar', 'flacso-uruguay');
            $description = __('La configuración necesaria para Pixel + CAPI está completa y ya podés ejecutar una prueba segura contra Meta.', 'flacso-uruguay');
        }

        $checks[] = [
            'label' => __('Pixel en navegador', 'flacso-uruguay'),
            'status' => $pixel_ready ? __('Listo', 'flacso-uruguay') : (empty($meta['enabled']) ? __('Apagado', 'flacso-uruguay') : __('Falta ID', 'flacso-uruguay')),
            'variant' => $pixel_ready ? 'success' : (empty($meta['enabled']) ? 'neutral' : 'danger'),
            'description' => $pixel_ready
                ? __('El plugin podrá cargar el Pixel en el frontend usando el ID guardado.', 'flacso-uruguay')
                : __('Necesitás activar Meta y guardar un Pixel ID válido.', 'flacso-uruguay'),
        ];

        $checks[] = [
            'label' => __('CAPI server-side', 'flacso-uruguay'),
            'status' => $capi_ready ? __('Listo', 'flacso-uruguay') : (empty($meta['enabled']) ? __('Apagado', 'flacso-uruguay') : __('Falta token', 'flacso-uruguay')),
            'variant' => $capi_ready ? 'success' : (empty($meta['enabled']) ? 'neutral' : 'warning'),
            'description' => $capi_ready
                ? __('WordPress ya tiene credenciales para enviar eventos a Conversions API.', 'flacso-uruguay')
                : __('Sin Access Token, WordPress no puede reenviar eventos a Meta desde servidor.', 'flacso-uruguay'),
        ];

        $checks[] = [
            'label' => __('Modo de prueba', 'flacso-uruguay'),
            'status' => $last_test_success ? __('Verificado', 'flacso-uruguay') : ($test_ready ? __('Configurado', 'flacso-uruguay') : __('Falta código', 'flacso-uruguay')),
            'variant' => $last_test_success ? 'success' : ($test_ready ? 'success' : 'warning'),
            'description' => $test_ready
                ? sprintf(
                    /* translators: %s: test event code */
                    __('Se enviarán pruebas seguras usando el código %s.', 'flacso-uruguay'),
                    (string) $meta['test_event_code']
                )
                : __('Agregá un Test Event Code para verificar CAPI sin disparar eventos reales.', 'flacso-uruguay'),
        ];

        $checks[] = [
            'label' => __('PageView automático', 'flacso-uruguay'),
            'status' => $pageview_ready ? __('Activo', 'flacso-uruguay') : __('Desactivado', 'flacso-uruguay'),
            'variant' => $pageview_ready ? 'success' : 'neutral',
            'description' => $pageview_ready
                ? __('Además de los eventos específicos, el plugin emitirá un PageView global.', 'flacso-uruguay')
                : __('Solo se enviarán los eventos específicos que dispare el sitio.', 'flacso-uruguay'),
        ];

        return [
            'variant' => $variant,
            'label' => $label,
            'description' => $description,
            'checks' => $checks,
        ];
    }

    private static function render_meta_last_test_result(array $result): void {
        if (empty($result['status'])) {
            return;
        }

        $variant = $result['status'] === 'success' ? 'success' : 'danger';
        $label = $result['status'] === 'success'
            ? __('Última prueba: exitosa', 'flacso-uruguay')
            : __('Última prueba: fallida', 'flacso-uruguay');

        $timestamp = !empty($result['timestamp']) ? absint($result['timestamp']) : 0;
        $formatted_date = $timestamp > 0
            ? wp_date('d/m/Y H:i:s', $timestamp, wp_timezone())
            : '';
        ?>
        <div class="flacso-meta-last-test">
            <h4><?php esc_html_e('Última prueba guardada', 'flacso-uruguay'); ?></h4>
            <p>
                <?php self::render_meta_status_badge($variant, $label); ?>
            </p>
            <ul>
                <?php if ($formatted_date !== ''): ?>
                    <li><strong><?php esc_html_e('Fecha:', 'flacso-uruguay'); ?></strong> <?php echo esc_html($formatted_date); ?></li>
                <?php endif; ?>
                <?php if (!empty($result['http_code'])): ?>
                    <li><strong><?php esc_html_e('HTTP:', 'flacso-uruguay'); ?></strong> <?php echo esc_html((string) $result['http_code']); ?></li>
                <?php endif; ?>
                <?php if (isset($result['events_received'])): ?>
                    <li><strong><?php esc_html_e('Events received:', 'flacso-uruguay'); ?></strong> <?php echo esc_html((string) $result['events_received']); ?></li>
                <?php endif; ?>
                <?php if (!empty($result['fbtrace_id'])): ?>
                    <li><strong><?php esc_html_e('fbtrace_id:', 'flacso-uruguay'); ?></strong> <code><?php echo esc_html((string) $result['fbtrace_id']); ?></code></li>
                <?php endif; ?>
                <?php if (!empty($result['message'])): ?>
                    <li><strong><?php esc_html_e('Detalle:', 'flacso-uruguay'); ?></strong> <?php echo esc_html((string) $result['message']); ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }

    private static function render_meta_leads_permission_result(array $result): void {
        if (empty($result['checked_at']) || empty($result['checks']) || !is_array($result['checks'])) {
            return;
        }

        $checked_at = absint($result['checked_at']);
        $formatted_date = $checked_at > 0
            ? wp_date('d/m/Y H:i:s', $checked_at, wp_timezone())
            : '';
        ?>
        <div class="flacso-meta-last-test">
            <h4><?php esc_html_e('Último chequeo de permisos', 'flacso-uruguay'); ?></h4>
            <?php if ($formatted_date !== ''): ?>
                <p><?php echo esc_html(sprintf(__('Ejecutado el %s.', 'flacso-uruguay'), $formatted_date)); ?></p>
            <?php endif; ?>
            <div class="flacso-meta-checklist">
                <?php foreach ($result['checks'] as $check): ?>
                    <?php
                    $variant = isset($check['ok']) && $check['ok'] ? 'success' : 'danger';
                    $label = isset($check['ok']) && $check['ok']
                        ? __('Correcto', 'flacso-uruguay')
                        : __('Revisar', 'flacso-uruguay');
                    ?>
                    <div class="flacso-meta-check">
                        <div>
                            <strong><?php echo esc_html((string) ($check['label'] ?? '')); ?></strong>
                            <p><?php echo esc_html((string) ($check['message'] ?? '')); ?></p>
                        </div>
                        <?php self::render_meta_status_badge($variant, $label); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function render_meta_status_badge(string $variant, string $label): void {
        $allowed = ['success', 'warning', 'danger', 'neutral'];
        if (!in_array($variant, $allowed, true)) {
            $variant = 'neutral';
        }
        ?>
        <span class="flacso-meta-status-badge flacso-meta-status-badge--<?php echo esc_attr($variant); ?>">
            <?php echo esc_html($label); ?>
        </span>
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
                <p class="flacso-integrations-lead"><?php esc_html_e('Define el endpoint que recibe las preinscripciones académicas. La integración soportada es la app Next.js en /api/preinscripciones/ofertas.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_input_field(
                    self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL,
                    __('Webhook de preinscripciones', 'flacso-uruguay'),
                    'url',
                    'https://tu-editor.com/api/preinscripciones/ofertas',
                    __('Si este campo queda vacío, el plugin intentará derivarlo desde la URL base del editor externo.', 'flacso-uruguay')
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

    private static function render_nav_announcement_card(): void {
        ?>
        <section class="flacso-integrations-card card-nav-announcement">
            <div class="flacso-card-header">
                <div class="flacso-card-icon-title">
                    <span class="flacso-card-icon">📣</span>
                    <h2><?php esc_html_e('Anuncio debajo del navbar', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Muestra una franja clickeable justo debajo del header de Kadence. Es ideal para anunciar aperturas, convocatorias o campañas temporales.', 'flacso-uruguay'); ?></p>
            </div>

            <div class="flacso-card-body">
                <?php
                self::render_checkbox_field(
                    self::OPTION_NAV_ANNOUNCEMENT_ENABLED,
                    __('Mostrar anuncio global', 'flacso-uruguay'),
                    __('Activa o desactiva el anuncio sin borrar su contenido.', 'flacso-uruguay')
                );

                self::render_input_field(
                    self::OPTION_NAV_ANNOUNCEMENT_URL,
                    __('URL de destino', 'flacso-uruguay'),
                    'url',
                    'https://flacso.edu.uy/tipo-oferta/diploma/',
                    __('Toda la franja funciona como enlace. Usa una URL absoluta.', 'flacso-uruguay')
                );

                self::render_input_field(
                    self::OPTION_NAV_ANNOUNCEMENT_KICKER,
                    __('Etiqueta izquierda', 'flacso-uruguay'),
                    'text',
                    'Próxima apertura',
                    __('Texto corto destacado al inicio del anuncio.', 'flacso-uruguay')
                );

                self::render_input_field(
                    self::OPTION_NAV_ANNOUNCEMENT_MESSAGE,
                    __('Mensaje principal', 'flacso-uruguay'),
                    'text',
                    'Diplomas 2026 · Segundo semestre',
                    __('Mensaje central que se repite dentro del ticker.', 'flacso-uruguay')
                );

                self::render_input_field(
                    self::OPTION_NAV_ANNOUNCEMENT_CTA,
                    __('Texto del botón', 'flacso-uruguay'),
                    'text',
                    'Postúlate ahora',
                    __('Texto del badge verde al final del anuncio.', 'flacso-uruguay')
                );

                self::render_input_field(
                    self::OPTION_NAV_ANNOUNCEMENT_ARIA,
                    __('Etiqueta accesible', 'flacso-uruguay'),
                    'text',
                    'Próxima apertura de diplomas FLACSO Uruguay',
                    __('Describe el objetivo del enlace para lectores de pantalla. Si lo dejas vacío, se genera automáticamente.', 'flacso-uruguay')
                );

                self::render_checkbox_field(
                    self::OPTION_NAV_ANNOUNCEMENT_HIDE_FORMACION,
                    __('Ocultar en /formacion', 'flacso-uruguay'),
                    __('Replica el comportamiento actual y evita mostrar el anuncio en la página índice de formación.', 'flacso-uruguay')
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
                    <h2><?php esc_html_e('Mailjet Lista de difusión', 'flacso-uruguay'); ?></h2>
                </div>
                <p class="flacso-integrations-lead"><?php esc_html_e('Credenciales para el formulario de suscripción a la lista de difusión. Se usan para dar de alta contactos en una lista de Mailjet y enviar el correo de confirmación desde WordPress.', 'flacso-uruguay'); ?></p>
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
                self::render_input_field(
                    self::OPTION_MAILJET_SENDER_EMAIL,
                    __('Mailjet Sender Email', 'flacso-uruguay'),
                    'email',
                    'noreply@flacso.edu.uy',
                    __('Remitente validado en Mailjet que se usará para el correo de confirmación al suscriptor.', 'flacso-uruguay')
                );
                self::render_input_field(
                    self::OPTION_MAILJET_SENDER_NAME,
                    __('Mailjet Sender Name', 'flacso-uruguay'),
                    'text',
                    'FLACSO Uruguay',
                    __('Nombre visible del remitente para ese correo de confirmación.', 'flacso-uruguay')
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
                self::render_input_field(
                    self::OPTION_USD_EXCHANGE_RATE,
                    __('Tipo de cambio UYU a USD', 'flacso-uruguay'),
                    'number',
                    '40',
                    __('Valor utilizado para calcular el precio en dólares para eventos de Meta.', 'flacso-uruguay')
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

    private static function render_textarea_field(string $option_name, string $label, string $placeholder, string $description): void {
        $value = get_option($option_name, '');
        $value = is_scalar($value) ? (string) $value : '';
        ?>
        <div class="flacso-integrations-field">
            <label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label>
            <textarea
                id="<?php echo esc_attr($option_name); ?>"
                name="<?php echo esc_attr($option_name); ?>"
                class="large-text code"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                spellcheck="false"
            ><?php echo esc_textarea($value); ?></textarea>
            <p class="flacso-integrations-help"><?php echo esc_html($description); ?></p>
        </div>
        <?php
    }

    private static function render_checkbox_field(string $option_name, string $label, string $description): void {
        $checked = (bool) get_option($option_name, 0);
        ?>
        <div class="flacso-integrations-field flacso-integrations-field--checkbox">
            <span class="flacso-field-label"><?php echo esc_html($label); ?></span>
            <label class="flacso-toggle-control" for="<?php echo esc_attr($option_name); ?>">
                <input type="hidden" name="<?php echo esc_attr($option_name); ?>" value="0" />
                <input
                    id="<?php echo esc_attr($option_name); ?>"
                    name="<?php echo esc_attr($option_name); ?>"
                    type="checkbox"
                    value="1"
                    class="flacso-toggle-control__input"
                    <?php checked($checked); ?>
                />
                <span class="flacso-toggle-control__switch" aria-hidden="true"></span>
                <span class="flacso-toggle-control__labels">
                    <span class="flacso-toggle-control__state flacso-toggle-control__state--on"><?php esc_html_e('Activado', 'flacso-uruguay'); ?></span>
                    <span class="flacso-toggle-control__state flacso-toggle-control__state--off"><?php esc_html_e('Desactivado', 'flacso-uruguay'); ?></span>
                </span>
            </label>
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

    private static function render_migration_banner(): void {
        $external_editor_url = get_option(self::OPTION_EXTERNAL_EDITOR_URL, 'https://editor-flacso-uy.vercel.app');
        $settings_url = rtrim($external_editor_url, '/') . '/ajustes';
        ?>
        <div class="flacso-migration-banner" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #f8fafc; padding: 1.75rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1); display: flex; flex-direction: row; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; border-left: 5px solid #0284c7;">
            <div style="flex: 1; min-width: 280px;">
                <h3 style="margin: 0 0 0.5rem 0; color: #38bdf8; font-size: 1.15rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; font-family: system-ui;">
                    <span>⚙️</span> <?php esc_html_e('Administración Centralizada Activa', 'flacso-uruguay'); ?>
                </h3>
                <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem; line-height: 1.5; font-family: system-ui;">
                    <?php esc_html_e('Para evitar inconsistencias, te recomendamos administrar todas las credenciales e integraciones directamente desde el panel de Ajustes del Editor Externo.', 'flacso-uruguay'); ?>
                </p>
            </div>
            <div>
                <a href="<?php echo esc_url($settings_url); ?>" target="_blank" rel="noopener noreferrer" style="background: #0284c7; color: #ffffff; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; transition: background 0.2s ease; display: inline-flex; align-items: center; gap: 0.5rem; font-family: system-ui;">
                    <?php esc_html_e('Ir a Ajustes del Editor', 'flacso-uruguay'); ?> <span>↗</span>
                </a>
            </div>
        </div>
        <?php
    }

    private static function render_inline_notices(): void {
        self::render_meta_inline_notice();

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

    private static function render_meta_inline_notice(): void {
        if (!isset($_GET['flacso_meta_test'])) {
            return;
        }

        $status = sanitize_key(wp_unslash($_GET['flacso_meta_test']));
        $message = isset($_GET['flacso_meta_test_message'])
            ? sanitize_text_field((string) wp_unslash($_GET['flacso_meta_test_message']))
            : '';
        $code = isset($_GET['flacso_meta_test_code']) ? absint(wp_unslash($_GET['flacso_meta_test_code'])) : 0;
        $class = $status === 'success' ? 'notice notice-success' : 'notice notice-error';
        $title = $status === 'success'
            ? __('Prueba de Meta CAPI exitosa', 'flacso-uruguay')
            : __('Prueba de Meta CAPI fallida', 'flacso-uruguay');

        echo '<div class="' . esc_attr($class) . '"><p><strong>' . esc_html($title) . '.</strong> ';
        echo esc_html($message);
        if ($code > 0) {
            echo ' ' . esc_html(sprintf(__('HTTP %d.', 'flacso-uruguay'), $code));
        }
        echo '</p></div>';
    }
}

FLACSO_Integrations_Settings::init();
