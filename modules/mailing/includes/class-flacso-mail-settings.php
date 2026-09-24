<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Consola avanzada de Correo y Mailjet (admin.php?page=flacso-correos).
 *
 * Este componente es el único dueño de la configuración Mailjet visible en
 * WordPress. Mantiene las mismas option keys para no requerir migración de datos
 * y añade:
 * - Diagnóstico en vivo de conexión Mailjet y métricas reales de entrega PostgreSQL (24h / 7d)
 * - Selector interactivo de listas de difusión de Mailjet con conteo de suscriptores
 * - Asignación de listas personalizadas de Mailjet por Oferta Académica y por Seminario
 * - Probador de envío transaccional en vivo (Oferta abierta, Oferta cerrada, Seminario)
 * - Previsualizador del HTML institucional de respaldo
 */
final class FLACSO_Mail_Settings {
    public const PAGE_SLUG = 'flacso-correos';
    private const SETTINGS_GROUP = 'flacso_correos_group';

    private const OPTION_API_KEY = 'flacso_mailjet_api_key';
    private const OPTION_SECRET_KEY = 'flacso_mailjet_secret_key';
    private const OPTION_LIST_ID = 'flacso_mailjet_list_id';
    private const OPTION_SENDER_EMAIL = 'flacso_mailjet_sender_email';
    private const OPTION_SENDER_NAME = 'flacso_mailjet_sender_name';
    private const OPTION_TEMPLATE_OPEN = 'flacso_mailjet_template_consulta_abierta';
    private const OPTION_TEMPLATE_CLOSED = 'flacso_mailjet_template_consulta_cerrada';
    private const OPTION_TEMPLATE_SEMINAR = 'flacso_mailjet_template_consulta_seminario';

    private const OPTION_OFFER_LISTS = 'flacso_mailjet_offer_lists';
    private const OPTION_SEMINAR_LISTS = 'flacso_mailjet_seminar_lists';
    private const OPTION_SYNC_INQUIRIES_GLOBAL = 'flacso_mailjet_sync_inquiries_to_global';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_menu'], 20);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('wp_ajax_flacso_mail_refresh_lists', [self::class, 'ajax_refresh_lists']);
        add_action('wp_ajax_flacso_mail_send_test', [self::class, 'ajax_send_test']);
        add_action('wp_ajax_flacso_mail_preview_html', [self::class, 'ajax_preview_html']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            FLACSO_Admin_Panel::PAGE_SLUG,
            __('Correos y Mailjet', 'flacso-uruguay'),
            __('Correos', 'flacso-uruguay'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function register_settings(): void {
        register_setting(self::SETTINGS_GROUP, self::OPTION_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_SECRET_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_LIST_ID, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_numeric_id'],
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_SENDER_EMAIL, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email'),
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_SENDER_NAME, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        ]);
        foreach ([self::OPTION_TEMPLATE_OPEN, self::OPTION_TEMPLATE_CLOSED, self::OPTION_TEMPLATE_SEMINAR] as $option) {
            register_setting(self::SETTINGS_GROUP, $option, [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_numeric_id'],
                'default' => '',
            ]);
        }
        register_setting(self::SETTINGS_GROUP, self::OPTION_OFFER_LISTS, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_entity_list_map'],
            'default' => [],
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_SEMINAR_LISTS, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_entity_list_map'],
            'default' => [],
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_SYNC_INQUIRIES_GLOBAL, [
            'type' => 'string',
            'sanitize_callback' => static function ($val): string {
                return !empty($val) && $val !== '0' ? '1' : '0';
            },
            'default' => '1',
        ]);
    }

    public static function sanitize_numeric_id($value): string {
        return preg_replace('/[^0-9]/', '', (string) $value) ?: '';
    }

    public static function sanitize_entity_list_map($input): array {
        if (!is_array($input)) {
            return [];
        }
        $clean = [];
        foreach ($input as $post_id => $list_id) {
            $pid = absint($post_id);
            $lid = self::sanitize_numeric_id($list_id);
            if ($pid > 0 && $lid !== '') {
                $clean[$pid] = $lid;
            }
        }
        return $clean;
    }

    public static function get_settings(): array {
        $offer_lists = get_option(self::OPTION_OFFER_LISTS, []);
        $seminar_lists = get_option(self::OPTION_SEMINAR_LISTS, []);

        $raw_sender_email = (string) get_option(self::OPTION_SENDER_EMAIL, get_option('admin_email', ''));
        $sender_email = function_exists('sanitize_email') ? sanitize_email($raw_sender_email) : trim($raw_sender_email);

        $default_site_name = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : 'FLACSO Uruguay';
        if (function_exists('wp_specialchars_decode')) {
            $default_site_name = wp_specialchars_decode($default_site_name, ENT_QUOTES);
        }
        $sender_name = trim((string) get_option(self::OPTION_SENDER_NAME, $default_site_name));

        return [
            'api_key' => trim((string) get_option(self::OPTION_API_KEY, '')),
            'secret_key' => trim((string) get_option(self::OPTION_SECRET_KEY, '')),
            'list_id' => trim((string) get_option(self::OPTION_LIST_ID, '')),
            'sender_email' => $sender_email,
            'sender_name' => $sender_name,
            'templates' => [
                'consulta_abierta' => trim((string) get_option(self::OPTION_TEMPLATE_OPEN, '')),
                'consulta_cerrada' => trim((string) get_option(self::OPTION_TEMPLATE_CLOSED, '')),
                'consulta_seminario' => trim((string) get_option(self::OPTION_TEMPLATE_SEMINAR, '')),
            ],
            'offer_lists' => is_array($offer_lists) ? $offer_lists : [],
            'seminar_lists' => is_array($seminar_lists) ? $seminar_lists : [],
            'sync_inquiries_to_global' => (string) get_option(self::OPTION_SYNC_INQUIRIES_GLOBAL, '1') === '1',
        ];
    }

    /**
     * Devuelve los IDs de listas de Mailjet donde debe sincronizarse una consulta de Oferta Académica.
     */
    public static function get_target_lists_for_offer(int $offer_wp_id): array {
        $settings = self::get_settings();
        $lists = [];
        if ($offer_wp_id > 0 && !empty($settings['offer_lists'][$offer_wp_id])) {
            $lists[] = (string) $settings['offer_lists'][$offer_wp_id];
        }
        if ($settings['sync_inquiries_to_global'] && $settings['list_id'] !== '') {
            $lists[] = $settings['list_id'];
        }
        return array_values(array_unique(array_filter($lists)));
    }

    /**
     * Devuelve los IDs de listas de Mailjet donde debe sincronizarse una consulta de Seminario.
     */
    public static function get_target_lists_for_seminar(int $seminar_wp_id): array {
        $settings = self::get_settings();
        $lists = [];
        if ($seminar_wp_id > 0 && !empty($settings['seminar_lists'][$seminar_wp_id])) {
            $lists[] = (string) $settings['seminar_lists'][$seminar_wp_id];
        }
        if ($settings['sync_inquiries_to_global'] && $settings['list_id'] !== '') {
            $lists[] = $settings['list_id'];
        }
        return array_values(array_unique(array_filter($lists)));
    }

    public static function is_transactional_ready(): bool {
        $settings = self::get_settings();
        return $settings['api_key'] !== ''
            && $settings['secret_key'] !== ''
            && $settings['sender_email'] !== '';
    }

    public static function is_mailing_ready(): bool {
        $settings = self::get_settings();
        return self::is_transactional_ready() && $settings['list_id'] !== '';
    }

    public static function get_contact_lists(bool $force_refresh = false): array {
        $settings = self::get_settings();
        if ($settings['api_key'] === '' || $settings['secret_key'] === '') {
            return [];
        }

        $cache_key = 'flacso_mailjet_lists_v3_' . md5($settings['api_key'] . '|' . $settings['secret_key']);
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        } else {
            delete_transient($cache_key);
        }

        $auth_header = 'Basic ' . base64_encode($settings['api_key'] . ':' . $settings['secret_key']);
        $limit = 1000;
        $offset = 0;
        $items = [];

        do {
            $response = wp_remote_get(
                add_query_arg(['Limit' => $limit, 'Offset' => $offset], 'https://api.mailjet.com/v3/REST/contactslist'),
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

            $decoded = json_decode(wp_remote_retrieve_body($response), true);
            $page_items = isset($decoded['Data']) && is_array($decoded['Data']) ? $decoded['Data'] : [];
            $items = array_merge($items, $page_items);

            $page_count = count($page_items);
            $total = isset($decoded['Total']) ? absint($decoded['Total']) : 0;
            $offset += $page_count;
        } while ($page_count === $limit || ($total > 0 && $offset < $total));

        $lists = [];
        foreach ($items as $item) {
            if (!is_array($item) || !empty($item['IsDeleted'])) {
                continue;
            }

            $list_id = self::sanitize_numeric_id($item['ID'] ?? '');
            if ($list_id === '') {
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

    public static function ajax_refresh_lists(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos'], 403);
        }
        check_ajax_referer('flacso_mail_console_nonce', 'nonce');
        $lists = self::get_contact_lists(true);
        wp_send_json_success([
            'lists' => $lists,
            'count' => count($lists),
        ]);
    }

    public static function ajax_send_test(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos'], 403);
        }
        check_ajax_referer('flacso_mail_console_nonce', 'nonce');
        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        $scenario = sanitize_key((string) ($_POST['scenario'] ?? 'consulta_abierta'));
        if (!in_array($scenario, ['consulta_abierta', 'consulta_cerrada', 'consulta_seminario'], true)) {
            $scenario = 'consulta_abierta';
        }
        if (!class_exists('FLACSO_Mailjet_Client')) {
            wp_send_json_error(['message' => 'FLACSO_Mailjet_Client no disponible'], 500);
        }
        $result = FLACSO_Mailjet_Client::send_test_scenario($email, $scenario);
        if (!empty($result['ok'])) {
            wp_send_json_success($result);
        }
        wp_send_json_error($result, 422);
    }

    public static function ajax_preview_html(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos'], 403);
        }
        check_ajax_referer('flacso_mail_console_nonce', 'nonce');
        $scenario = sanitize_key((string) ($_GET['scenario'] ?? $_POST['scenario'] ?? 'consulta_abierta'));
        if (!in_array($scenario, ['consulta_abierta', 'consulta_cerrada', 'consulta_seminario'], true)) {
            $scenario = 'consulta_abierta';
        }
        if (!class_exists('FLACSO_Mailjet_Client')) {
            wp_send_json_error(['message' => 'FLACSO_Mailjet_Client no disponible'], 500);
        }
        $preview = FLACSO_Mailjet_Client::get_preview_html($scenario);
        wp_send_json_success($preview);
    }

    public static function get_page_url(array $args = []): string {
        return add_query_arg(
            array_merge(['page' => self::PAGE_SLUG], $args),
            admin_url('admin.php')
        );
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        $transactional_ready = self::is_transactional_ready();
        $mailing_ready = self::is_mailing_ready();
        $force_refresh = !empty($_GET['refresh_lists']);
        $lists = self::get_contact_lists($force_refresh);
        $total_subscribers = array_sum(array_column($lists, 'subscribers'));

        $metrics = class_exists('FLACSO_Inquiry_Analytics_Repository')
            ? FLACSO_Inquiry_Analytics_Repository::get_email_delivery_metrics()
            : ['db_connected' => false, 'last_24h' => ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'rate' => 100], 'last_7d' => ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'rate' => 100]];

        // Obtener Ofertas Académicas y Seminarios publicados para mapeo de listas
        $offers = get_posts([
            'post_type'      => 'oferta-academica',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        $seminars = get_posts([
            'post_type'      => 'seminario',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $nonce = wp_create_nonce('flacso_mail_console_nonce');
        $active_tab = sanitize_key((string) ($_GET['tab'] ?? 'config'));
        if (!in_array($active_tab, ['config', 'lists', 'tester'], true)) {
            $active_tab = 'config';
        }
        ?>
        <style>
            .flacso-mail-console { max-width: 1280px; margin: 18px 20px 40px 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; }
            .flacso-mail-hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); color: #fff; border-radius: 16px; padding: 24px 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.14); margin-bottom: 20px; }
            .flacso-mail-hero h1 { color: #fff; margin: 4px 0 6px; font-size: 24px; font-weight: 700; }
            .flacso-mail-hero p { color: #cbd5e1; margin: 0; font-size: 14px; max-width: 720px; }
            .flacso-mail-eyebrow { text-transform: uppercase; letter-spacing: 0.08em; font-size: 11px; font-weight: 700; color: #93c5fd; margin: 0; }
            .flacso-mail-hero-actions { display: flex; gap: 10px; flex-wrap: wrap; }
            .flacso-mail-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; text-decoration: none; cursor: pointer; border: 1px solid transparent; transition: all 0.15s ease; }
            .flacso-mail-btn-light { background: rgba(255,255,255,0.14); color: #fff; border-color: rgba(255,255,255,0.24); }
            .flacso-mail-btn-light:hover { background: rgba(255,255,255,0.24); color: #fff; }
            .flacso-mail-btn-primary { background: #2563eb; color: #fff; border-color: #1d4ed8; }
            .flacso-mail-btn-primary:hover { background: #1d4ed8; color: #fff; }
            .flacso-mail-btn-secondary { background: #f8fafc; color: #1e293b; border-color: #cbd5e1; }
            .flacso-mail-btn-secondary:hover { background: #f1f5f9; color: #0f172a; }
            .flacso-mail-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin-bottom: 22px; }
            .flacso-mail-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 18px; box-shadow: 0 4px 12px rgba(15,23,42,0.04); }
            .flacso-mail-kpi-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; display: flex; justify-content: space-between; align-items: center; }
            .flacso-mail-kpi-value { font-size: 26px; font-weight: 800; color: #0f172a; margin: 6px 0 4px; }
            .flacso-mail-kpi-sub { font-size: 12.5px; color: #475569; }
            .flacso-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
            .flacso-badge-ok { background: #dcfce7; color: #166534; }
            .flacso-badge-warn { background: #fef3c7; color: #92400e; }
            .flacso-badge-err { background: #fee2e2; color: #991b1b; }
            .flacso-badge-info { background: #dbeafe; color: #1e40af; }
            .flacso-mail-tabs { display: flex; gap: 8px; border-bottom: 1px solid #cbd5e1; margin-bottom: 20px; padding-bottom: 0; }
            .flacso-mail-tab { padding: 10px 18px; font-size: 13.5px; font-weight: 700; color: #475569; text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -1px; cursor: pointer; background: none; border-top: none; border-left: none; border-right: none; }
            .flacso-mail-tab.active { color: #1d4ed8; border-bottom-color: #1d4ed8; background: #fff; border-radius: 8px 8px 0 0; }
            .flacso-mail-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px 24px; margin-bottom: 20px; box-shadow: 0 4px 14px rgba(15,23,42,0.04); }
            .flacso-mail-card h2 { margin: 0 0 6px; font-size: 18px; font-weight: 700; color: #0f172a; }
            .flacso-mail-card p.desc { margin: 0 0 18px; color: #64748b; font-size: 13.5px; }
            .flacso-grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }
            .flacso-field-group { margin-bottom: 14px; }
            .flacso-field-group label { display: block; font-weight: 600; font-size: 13px; color: #1e293b; margin-bottom: 6px; }
            .flacso-field-group input[type="text"], .flacso-field-group input[type="email"], .flacso-field-group input[type="password"], .flacso-field-group select { width: 100%; max-width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13.5px; }
            .flacso-template-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; background: #f8fafc; flex-wrap: wrap; }
            .flacso-mapping-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .flacso-mapping-table th { text-align: left; padding: 10px 12px; background: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; border-bottom: 1px solid #cbd5e1; }
            .flacso-mapping-table td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
            .flacso-mapping-scroll { max-height: 420px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
            .flacso-preview-frame { width: 100%; height: 480px; border: 1px solid #cbd5e1; border-radius: 10px; background: #f8fafc; }
        </style>

        <div class="wrap flacso-mail-console">
            <header class="flacso-mail-hero">
                <div>
                    <p class="flacso-mail-eyebrow"><?php esc_html_e('Comunicaciones Transaccionales y Listas', 'flacso-uruguay'); ?></p>
                    <h1><?php esc_html_e('Consola de Correos y Mailjet', 'flacso-uruguay'); ?></h1>
                    <p><?php esc_html_e('Administración centralizada de credenciales Mailjet, plantillas transaccionales con HTML de respaldo, asignación de listas por Oferta/Seminario y probador en vivo.', 'flacso-uruguay'); ?></p>
                </div>
                <div class="flacso-mail-hero-actions">
                    <a class="flacso-mail-btn flacso-mail-btn-light" href="<?php echo esc_url(admin_url('admin.php?page=flacso-consultas')); ?>">
                        <span class="dashicons dashicons-Chart-bar"></span>
                        <?php esc_html_e('Plataforma de Consultas', 'flacso-uruguay'); ?>
                    </a>
                    <a class="flacso-mail-btn flacso-mail-btn-light" href="<?php echo esc_url(self::get_page_url(['refresh_lists' => '1', 'tab' => $active_tab])); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refrescar Listas Mailjet', 'flacso-uruguay'); ?>
                    </a>
                </div>
            </header>

            <?php settings_errors(); ?>

            <!-- KPIs superiores -->
            <section class="flacso-mail-kpis">
                <div class="flacso-mail-kpi">
                    <div class="flacso-mail-kpi-label">
                        <span><?php esc_html_e('Conexión Mailjet API', 'flacso-uruguay'); ?></span>
                        <span class="flacso-badge <?php echo $transactional_ready ? 'flacso-badge-ok' : 'flacso-badge-warn'; ?>">
                            <?php echo $transactional_ready ? esc_html__('ACTIVA', 'flacso-uruguay') : esc_html__('PENDIENTE', 'flacso-uruguay'); ?>
                        </span>
                    </div>
                    <div class="flacso-mail-kpi-value"><?php echo esc_html(number_format_i18n(count($lists))); ?> <?php esc_html_e('listas', 'flacso-uruguay'); ?></div>
                    <div class="flacso-mail-kpi-sub">
                        <strong><?php echo esc_html(number_format_i18n($total_subscribers)); ?></strong> <?php esc_html_e('suscriptores totales · Remitente:', 'flacso-uruguay'); ?>
                        <code><?php echo esc_html($settings['sender_email'] ?: 'sin definir'); ?></code>
                    </div>
                </div>

                <div class="flacso-mail-kpi">
                    <div class="flacso-mail-kpi-label">
                        <span><?php esc_html_e('Entregas (Últimas 24h)', 'flacso-uruguay'); ?></span>
                        <span class="flacso-badge <?php echo ($metrics['last_24h']['failed'] ?? 0) === 0 ? 'flacso-badge-ok' : 'flacso-badge-warn'; ?>">
                            <?php echo esc_html((string) ($metrics['last_24h']['rate'] ?? 100)); ?>% <?php esc_html_e('éxito', 'flacso-uruguay'); ?>
                        </span>
                    </div>
                    <div class="flacso-mail-kpi-value"><?php echo esc_html(number_format_i18n((int) ($metrics['last_24h']['sent'] ?? 0))); ?> <?php esc_html_e('enviados', 'flacso-uruguay'); ?></div>
                    <div class="flacso-mail-kpi-sub">
                        <span style="color:#b91c1c;font-weight:600;"><?php echo esc_html(number_format_i18n((int) ($metrics['last_24h']['failed'] ?? 0))); ?> <?php esc_html_e('fallidos', 'flacso-uruguay'); ?></span> ·
                        <span><?php echo esc_html(number_format_i18n((int) ($metrics['last_24h']['skipped'] ?? 0))); ?> <?php esc_html_e('omitidos', 'flacso-uruguay'); ?></span>
                    </div>
                </div>

                <div class="flacso-mail-kpi">
                    <div class="flacso-mail-kpi-label">
                        <span><?php esc_html_e('Entregas (Últimos 7 días)', 'flacso-uruguay'); ?></span>
                        <span class="flacso-badge flacso-badge-info">
                            <?php echo esc_html((string) ($metrics['last_7d']['rate'] ?? 100)); ?>% <?php esc_html_e('entrega', 'flacso-uruguay'); ?>
                        </span>
                    </div>
                    <div class="flacso-mail-kpi-value"><?php echo esc_html(number_format_i18n((int) ($metrics['last_7d']['sent'] ?? 0))); ?> <?php esc_html_e('enviados', 'flacso-uruguay'); ?></div>
                    <div class="flacso-mail-kpi-sub">
                        <span style="color:#b91c1c;font-weight:600;"><?php echo esc_html(number_format_i18n((int) ($metrics['last_7d']['failed'] ?? 0))); ?> <?php esc_html_e('fallidos', 'flacso-uruguay'); ?></span> ·
                        <span><?php echo esc_html(number_format_i18n((int) ($metrics['last_7d']['total'] ?? 0))); ?> <?php esc_html_e('consultas totales', 'flacso-uruguay'); ?></span>
                    </div>
                </div>

                <div class="flacso-mail-kpi">
                    <div class="flacso-mail-kpi-label">
                        <span><?php esc_html_e('Listas por Oferta / Seminario', 'flacso-uruguay'); ?></span>
                        <span class="flacso-badge flacso-badge-ok">
                            <?php echo $mailing_ready ? esc_html__('GLOBAL OK', 'flacso-uruguay') : esc_html__('PERSONALIZABLE', 'flacso-uruguay'); ?>
                        </span>
                    </div>
                    <div class="flacso-mail-kpi-value">
                        <?php echo esc_html(number_format_i18n(count($settings['offer_lists']) + count($settings['seminar_lists']))); ?> <?php esc_html_e('asignadas', 'flacso-uruguay'); ?>
                    </div>
                    <div class="flacso-mail-kpi-sub">
                        <?php echo esc_html(sprintf('%d ofertas · %d seminarios con lista propia', count($settings['offer_lists']), count($settings['seminar_lists']))); ?>
                    </div>
                </div>
            </section>

            <!-- Navegación de pestañas -->
            <nav class="flacso-mail-tabs" aria-label="Secciones de Correos">
                <button type="button" class="flacso-mail-tab <?php echo $active_tab === 'config' ? 'active' : ''; ?>" data-target-tab="config">
                    1. Credenciales y Plantillas Transaccionales
                </button>
                <button type="button" class="flacso-mail-tab <?php echo $active_tab === 'lists' ? 'active' : ''; ?>" data-target-tab="lists">
                    2. Lista Global y Listas por Oferta / Seminario (<?php echo esc_html((string) (count($settings['offer_lists']) + count($settings['seminar_lists']))); ?>)
                </button>
                <button type="button" class="flacso-mail-tab <?php echo $active_tab === 'tester' ? 'active' : ''; ?>" data-target-tab="tester">
                    3. Probador en Vivo y Vista Previa HTML
                </button>
            </nav>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <!-- TAB 1: CREDENCIALES Y PLANTILLAS -->
                <div class="flacso-mail-tab-panel" id="flacso-mail-panel-config" style="<?php echo $active_tab === 'config' ? '' : 'display:none;'; ?>">
                    <div class="flacso-mail-card">
                        <h2><?php esc_html_e('Cuenta Mailjet y Remitente Institucional', 'flacso-uruguay'); ?></h2>
                        <p class="desc"><?php esc_html_e('Credenciales empleadas por WordPress para el despacho transaccional directo (API v3.1) y sincronización de listas (API v3).', 'flacso-uruguay'); ?></p>

                        <div class="flacso-grid-2">
                            <div class="flacso-field-group">
                                <label for="<?php echo esc_attr(self::OPTION_API_KEY); ?>"><?php esc_html_e('Mailjet API Key', 'flacso-uruguay'); ?></label>
                                <input class="regular-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_API_KEY); ?>" name="<?php echo esc_attr(self::OPTION_API_KEY); ?>" value="<?php echo esc_attr($settings['api_key']); ?>">
                            </div>
                            <div class="flacso-field-group">
                                <label for="<?php echo esc_attr(self::OPTION_SECRET_KEY); ?>"><?php esc_html_e('Mailjet Secret Key', 'flacso-uruguay'); ?></label>
                                <input class="regular-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_SECRET_KEY); ?>" name="<?php echo esc_attr(self::OPTION_SECRET_KEY); ?>" value="<?php echo esc_attr($settings['secret_key']); ?>">
                            </div>
                            <div class="flacso-field-group">
                                <label for="<?php echo esc_attr(self::OPTION_SENDER_EMAIL); ?>"><?php esc_html_e('Correo Remitente Autorizado (From Email)', 'flacso-uruguay'); ?></label>
                                <input class="regular-text" type="email" id="<?php echo esc_attr(self::OPTION_SENDER_EMAIL); ?>" name="<?php echo esc_attr(self::OPTION_SENDER_EMAIL); ?>" value="<?php echo esc_attr($settings['sender_email']); ?>">
                            </div>
                            <div class="flacso-field-group">
                                <label for="<?php echo esc_attr(self::OPTION_SENDER_NAME); ?>"><?php esc_html_e('Nombre Visible del Remitente (From Name)', 'flacso-uruguay'); ?></label>
                                <input class="regular-text" type="text" id="<?php echo esc_attr(self::OPTION_SENDER_NAME); ?>" name="<?php echo esc_attr(self::OPTION_SENDER_NAME); ?>" value="<?php echo esc_attr($settings['sender_name']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="flacso-mail-card">
                        <h2><?php esc_html_e('Plantillas Transaccionales y Respaldo HTML Institucional', 'flacso-uruguay'); ?></h2>
                        <p class="desc"><?php esc_html_e('Regla conservadora activa: si ingresás un Template ID numérico de Mailjet, se enviará con esa plantilla. Si lo dejás vacío, WordPress genera y envía automáticamente el correo HTML institucional de respaldo.', 'flacso-uruguay'); ?></p>

                        <?php
                        $template_rows = [
                            self::OPTION_TEMPLATE_OPEN => [
                                'label'    => 'Consulta de Oferta con Inscripciones Abiertas',
                                'val'      => $settings['templates']['consulta_abierta'],
                                'scenario' => 'consulta_abierta',
                                'desc'     => 'Incluye enlaces al programa, carta descriptiva, calendario y botón directo de preinscripción.',
                            ],
                            self::OPTION_TEMPLATE_CLOSED => [
                                'label'    => 'Consulta de Oferta con Inscripciones Cerradas',
                                'val'      => $settings['templates']['consulta_cerrada'],
                                'scenario' => 'consulta_cerrada',
                                'desc'     => 'Informa la próxima cohorte estimada e invita a conocer el programa académico.',
                            ],
                            self::OPTION_TEMPLATE_SEMINAR => [
                                'label'    => 'Consulta de Seminario de Posgrado',
                                'val'      => $settings['templates']['consulta_seminario'],
                                'scenario' => 'consulta_seminario',
                                'desc'     => 'Incluye fecha de inicio, modalidad, créditos, enlace a la ficha y copia de la consulta realizada.',
                            ],
                        ];
                        foreach ($template_rows as $option => $row):
                            $has_tpl = ($row['val'] !== '' && is_numeric($row['val']) && (int) $row['val'] > 0);
                        ?>
                        <div class="flacso-template-row">
                            <div style="flex: 1 1 320px;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                    <label for="<?php echo esc_attr($option); ?>" style="font-weight:700;font-size:14px;color:#0f172a;margin:0;">
                                        <?php echo esc_html($row['label']); ?>
                                    </label>
                                    <span class="flacso-badge <?php echo $has_tpl ? 'flacso-badge-info' : 'flacso-badge-ok'; ?>">
                                        <?php echo $has_tpl ? esc_html('Mailjet Template #' . $row['val']) : esc_html('HTML Institucional Activo'); ?>
                                    </span>
                                </div>
                                <div style="font-size:12.5px;color:#64748b;"><?php echo esc_html($row['desc']); ?></div>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <input class="regular-text code" style="width:170px;" placeholder="Vacío = HTML interno" type="text" inputmode="numeric" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr($row['val']); ?>">
                                <button type="button" class="flacso-mail-btn flacso-mail-btn-secondary flacso-open-preview-btn" data-scenario="<?php echo esc_attr($row['scenario']); ?>">
                                    👁️ Ver HTML respaldo
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php submit_button(__('Guardar configuración de correos', 'flacso-uruguay'), 'primary large'); ?>
                </div>

                <!-- TAB 2: LISTAS DE DIFUSIÓN (GLOBAL + POR OFERTA Y SEMINARIO) -->
                <div class="flacso-mail-tab-panel" id="flacso-mail-panel-lists" style="<?php echo $active_tab === 'lists' ? '' : 'display:none;'; ?>">
                    <div class="flacso-mail-card">
                        <h2><?php esc_html_e('Lista de Difusión Global (Suscripción General y Consultas)', 'flacso-uruguay'); ?></h2>
                        <p class="desc"><?php esc_html_e('Seleccioná la lista principal de Mailjet para el formulario de suscripción al boletín y opcionalmente para todas las consultas recibidas.', 'flacso-uruguay'); ?></p>

                        <div class="flacso-grid-2">
                            <div class="flacso-field-group">
                                <label for="<?php echo esc_attr(self::OPTION_LIST_ID); ?>"><?php esc_html_e('Lista Global de Mailjet', 'flacso-uruguay'); ?></label>
                                <select id="<?php echo esc_attr(self::OPTION_LIST_ID); ?>" name="<?php echo esc_attr(self::OPTION_LIST_ID); ?>">
                                    <option value=""><?php esc_html_e('— Sin lista global seleccionada —', 'flacso-uruguay'); ?></option>
                                    <?php foreach ($lists as $list): ?>
                                        <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($settings['list_id'], $list['id']); ?>>
                                            <?php echo esc_html(sprintf('%s (ID: %s — %s suscriptores)', $list['name'], $list['id'], number_format_i18n($list['subscribers']))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($settings['list_id'] !== '' && !in_array($settings['list_id'], array_column($lists, 'id'), true)): ?>
                                        <option value="<?php echo esc_attr($settings['list_id']); ?>" selected>
                                            <?php echo esc_html('Lista ID #' . $settings['list_id'] . ' (ID manual)'); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="flacso-field-group" style="display:flex;flex-direction:column;justify-content:center;">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:18px;">
                                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_SYNC_INQUIRIES_GLOBAL); ?>" value="0">
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_SYNC_INQUIRIES_GLOBAL); ?>" value="1" <?php checked($settings['sync_inquiries_to_global']); ?>>
                                    <span><?php esc_html_e('Suscribir automáticamente a la Lista Global a quienes consulten en Ofertas o Seminarios', 'flacso-uruguay'); ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flacso-mail-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
                            <div>
                                <h2><?php esc_html_e('Asignación de Listas de Contactos por Oferta Académica y Seminario', 'flacso-uruguay'); ?></h2>
                                <p class="desc" style="margin-bottom:0;"><?php esc_html_e('Asigná una lista específica de Mailjet a cada posgrado o seminario. Cuando un interesado envíe una consulta sobre esa oferta/seminario, su contacto se agregará automáticamente a esa lista.', 'flacso-uruguay'); ?></p>
                            </div>
                            <input type="search" id="flacso-entity-list-search" placeholder="🔍 Filtrar oferta o seminario..." style="padding:8px 12px;border-radius:8px;border:1px solid #cbd5e1;min-width:260px;">
                        </div>

                        <h3 style="font-size:14px;margin:12px 0 8px;color:#1e3a8a;"><?php echo esc_html(sprintf('Ofertas Académicas (%d)', count($offers))); ?></h3>
                        <div class="flacso-mapping-scroll" style="margin-bottom:20px;">
                            <table class="flacso-mapping-table">
                                <thead>
                                    <tr>
                                        <th style="width:90px;">ID WP</th>
                                        <th>Oferta Académica</th>
                                        <th style="width:420px;">Lista de Contactos Mailjet Asignada</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($offers as $offer_post):
                                        $oid = (int) $offer_post->ID;
                                        $selected_lid = (string) ($settings['offer_lists'][$oid] ?? '');
                                    ?>
                                    <tr class="flacso-entity-map-row" data-search="<?php echo esc_attr(strtolower($offer_post->post_title . ' ' . $oid)); ?>">
                                        <td><code>#<?php echo esc_html((string) $oid); ?></code></td>
                                        <td>
                                            <strong><?php echo esc_html($offer_post->post_title ?: '(Sin título)'); ?></strong>
                                            <?php if ($offer_post->post_status !== 'publish'): ?>
                                                <span class="flacso-badge flacso-badge-warn"><?php echo esc_html($offer_post->post_status); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select name="<?php echo esc_attr(self::OPTION_OFFER_LISTS . '[' . $oid . ']'); ?>" style="width:100%;">
                                                <option value=""><?php esc_html_e('— Solo lista global (sin lista específica) —', 'flacso-uruguay'); ?></option>
                                                <?php foreach ($lists as $list): ?>
                                                    <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($selected_lid, $list['id']); ?>>
                                                        <?php echo esc_html(sprintf('%s (ID: %s — %s suscr.)', $list['name'], $list['id'], number_format_i18n($list['subscribers']))); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if ($selected_lid !== '' && !in_array($selected_lid, array_column($lists, 'id'), true)): ?>
                                                    <option value="<?php echo esc_attr($selected_lid); ?>" selected><?php echo esc_html('Lista ID #' . $selected_lid); ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <h3 style="font-size:14px;margin:12px 0 8px;color:#1e3a8a;"><?php echo esc_html(sprintf('Seminarios (%d)', count($seminars))); ?></h3>
                        <div class="flacso-mapping-scroll">
                            <table class="flacso-mapping-table">
                                <thead>
                                    <tr>
                                        <th style="width:90px;">ID WP</th>
                                        <th>Seminario</th>
                                        <th style="width:420px;">Lista de Contactos Mailjet Asignada</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seminars as $sem_post):
                                        $sid = (int) $sem_post->ID;
                                        $selected_slid = (string) ($settings['seminar_lists'][$sid] ?? '');
                                    ?>
                                    <tr class="flacso-entity-map-row" data-search="<?php echo esc_attr(strtolower($sem_post->post_title . ' ' . $sid)); ?>">
                                        <td><code>#<?php echo esc_html((string) $sid); ?></code></td>
                                        <td>
                                            <strong><?php echo esc_html($sem_post->post_title ?: '(Sin título)'); ?></strong>
                                            <?php if ($sem_post->post_status !== 'publish'): ?>
                                                <span class="flacso-badge flacso-badge-warn"><?php echo esc_html($sem_post->post_status); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select name="<?php echo esc_attr(self::OPTION_SEMINAR_LISTS . '[' . $sid . ']'); ?>" style="width:100%;">
                                                <option value=""><?php esc_html_e('— Solo lista global (sin lista específica) —', 'flacso-uruguay'); ?></option>
                                                <?php foreach ($lists as $list): ?>
                                                    <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($selected_slid, $list['id']); ?>>
                                                        <?php echo esc_html(sprintf('%s (ID: %s — %s suscr.)', $list['name'], $list['id'], number_format_i18n($list['subscribers']))); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if ($selected_slid !== '' && !in_array($selected_slid, array_column($lists, 'id'), true)): ?>
                                                    <option value="<?php echo esc_attr($selected_slid); ?>" selected><?php echo esc_html('Lista ID #' . $selected_slid); ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php submit_button(__('Guardar asignaciones de listas', 'flacso-uruguay'), 'primary large'); ?>
                </div>
            </form>

            <!-- TAB 3: PROBADOR EN VIVO Y PREVISUALIZADOR HTML -->
            <div class="flacso-mail-tab-panel" id="flacso-mail-panel-tester" style="<?php echo $active_tab === 'tester' ? '' : 'display:none;'; ?>">
                <div class="flacso-grid-2">
                    <div class="flacso-mail-card">
                        <h2><?php esc_html_e('Probador de Envío Transaccional en Vivo', 'flacso-uruguay'); ?></h2>
                        <p class="desc"><?php esc_html_e('Dispara un correo real a través de FLACSO_Mailjet_Client usando la configuración actual para verificar entrega, remitente y MessageUUID.', 'flacso-uruguay'); ?></p>

                        <div class="flacso-field-group">
                            <label for="flacso-test-scenario"><?php esc_html_e('Escenario a probar', 'flacso-uruguay'); ?></label>
                            <select id="flacso-test-scenario">
                                <option value="consulta_abierta">Oferta Académica — Inscripciones Abiertas</option>
                                <option value="consulta_cerrada">Oferta Académica — Inscripciones Cerradas</option>
                                <option value="consulta_seminario">Consulta de Seminario de Posgrado</option>
                            </select>
                        </div>

                        <div class="flacso-field-group">
                            <label for="flacso-test-recipient"><?php esc_html_e('Correo destinatario de la prueba', 'flacso-uruguay'); ?></label>
                            <input type="email" id="flacso-test-recipient" value="<?php echo esc_attr(wp_get_current_user()->user_email ?: $settings['sender_email']); ?>" placeholder="tu-correo@flacso.edu.uy">
                        </div>

                        <button type="button" class="flacso-mail-btn flacso-mail-btn-primary" id="flacso-run-test-email-btn">
                            🚀 Enviar correo de prueba ahora
                        </button>

                        <div id="flacso-test-email-result" style="margin-top:16px;display:none;padding:14px;border-radius:10px;font-size:13px;"></div>
                    </div>

                    <div class="flacso-mail-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <div>
                                <h2 style="margin:0;"><?php esc_html_e('Vista Previa del HTML Institucional', 'flacso-uruguay'); ?></h2>
                                <p class="desc" style="margin:2px 0 0;"><?php esc_html_e('Así se ve el correo HTML generado automáticamente por WordPress.', 'flacso-uruguay'); ?></p>
                            </div>
                            <select id="flacso-preview-scenario-select" style="padding:6px 10px;border-radius:8px;border:1px solid #cbd5e1;">
                                <option value="consulta_abierta">Oferta Abierta</option>
                                <option value="consulta_cerrada">Oferta Cerrada</option>
                                <option value="consulta_seminario">Seminario</option>
                            </select>
                        </div>
                        <div id="flacso-preview-subject" style="font-size:12.5px;font-weight:700;color:#1e3a8a;background:#eff6ff;padding:8px 12px;border-radius:6px;margin-bottom:10px;"></div>
                        <iframe id="flacso-preview-iframe" class="flacso-preview-frame" title="Vista previa HTML"></iframe>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const nonce = <?php echo wp_json_encode($nonce); ?>;

            // Tabs switching
            const tabs = document.querySelectorAll('.flacso-mail-tab');
            const panels = {
                config: document.getElementById('flacso-mail-panel-config'),
                lists: document.getElementById('flacso-mail-panel-lists'),
                tester: document.getElementById('flacso-mail-panel-tester')
            };

            function activateTab(tabKey) {
                tabs.forEach(t => t.classList.toggle('active', t.getAttribute('data-target-tab') === tabKey));
                Object.keys(panels).forEach(k => {
                    if (panels[k]) panels[k].style.display = (k === tabKey) ? '' : 'none';
                });
                if (tabKey === 'tester') {
                    loadPreview(document.getElementById('flacso-preview-scenario-select').value);
                }
            }

            tabs.forEach(tab => {
                tab.addEventListener('click', () => activateTab(tab.getAttribute('data-target-tab')));
            });

            // Quick preview buttons in Tab 1
            document.querySelectorAll('.flacso-open-preview-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const sc = btn.getAttribute('data-scenario');
                    const sel = document.getElementById('flacso-preview-scenario-select');
                    if (sel) sel.value = sc;
                    activateTab('tester');
                    loadPreview(sc);
                });
            });

            // Entity list search filter
            const searchInput = document.getElementById('flacso-entity-list-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.trim().toLowerCase();
                    document.querySelectorAll('.flacso-entity-map-row').forEach(row => {
                        const text = row.getAttribute('data-search') || '';
                        row.style.display = (!q || text.includes(q)) ? '' : 'none';
                    });
                });
            }

            // HTML Preview loader
            const previewSelect = document.getElementById('flacso-preview-scenario-select');
            const previewSubject = document.getElementById('flacso-preview-subject');
            const previewIframe = document.getElementById('flacso-preview-iframe');

            function loadPreview(scenario) {
                if (!previewIframe) return;
                fetch(ajaxUrl + '?action=flacso_mail_preview_html&nonce=' + encodeURIComponent(nonce) + '&scenario=' + encodeURIComponent(scenario))
                    .then(r => r.json())
                    .then(res => {
                        if (res && res.success && res.data) {
                            if (previewSubject) previewSubject.textContent = 'Asunto: ' + (res.data.subject || '');
                            previewIframe.srcdoc = res.data.html || '';
                        }
                    })
                    .catch(() => {});
            }

            if (previewSelect) {
                previewSelect.addEventListener('change', function() {
                    loadPreview(this.value);
                });
                if (<?php echo wp_json_encode($active_tab); ?> === 'tester') {
                    loadPreview(previewSelect.value);
                }
            }

            // Live Test Email Sender
            const testBtn = document.getElementById('flacso-run-test-email-btn');
            const testResult = document.getElementById('flacso-test-email-result');
            if (testBtn && testResult) {
                testBtn.addEventListener('click', function() {
                    const email = document.getElementById('flacso-test-recipient').value.trim();
                    const scenario = document.getElementById('flacso-test-scenario').value;
                    if (!email) {
                        alert('Ingresá un correo destinatario.');
                        return;
                    }
                    testBtn.disabled = true;
                    testBtn.textContent = '⏳ Enviando prueba a Mailjet...';
                    testResult.style.display = 'block';
                    testResult.style.background = '#f1f5f9';
                    testResult.style.color = '#334155';
                    testResult.textContent = 'Conectando con api.mailjet.com/v3.1/send...';

                    const body = new URLSearchParams({
                        action: 'flacso_mail_send_test',
                        nonce: nonce,
                        email: email,
                        scenario: scenario
                    });

                    fetch(ajaxUrl, { method: 'POST', body: body })
                        .then(r => r.json())
                        .then(res => {
                            testBtn.disabled = false;
                            testBtn.textContent = '🚀 Enviar correo de prueba ahora';
                            const d = res.data || {};
                            if (res.success && d.ok) {
                                testResult.style.background = '#dcfce7';
                                testResult.style.color = '#166534';
                                testResult.innerHTML = '<strong>✅ Correo enviado correctamente por Mailjet</strong><br>' +
                                    'Estado: <code>' + (d.status || 'sent') + '</code> · Remitente: <code>' + (d.sender || '') + '</code><br>' +
                                    'MessageID: <code>' + (d.message_id || 'N/A') + '</code><br>' +
                                    'MessageUUID: <code>' + (d.message_uuid || 'N/A') + '</code>';
                            } else {
                                testResult.style.background = '#fee2e2';
                                testResult.style.color = '#991b1b';
                                testResult.innerHTML = '<strong>❌ Falló el envío de prueba:</strong> ' + (d.error || d.message || 'Error desconocido');
                            }
                        })
                        .catch(err => {
                            testBtn.disabled = false;
                            testBtn.textContent = '🚀 Enviar correo de prueba ahora';
                            testResult.style.background = '#fee2e2';
                            testResult.style.color = '#991b1b';
                            testResult.textContent = 'Error de red: ' + err.message;
                        });
                });
            }
        })();
        </script>
        <?php
    }
}
