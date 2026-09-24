<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuración de correo y Mailjet.
 *
 * Este componente es el único dueño de la configuración Mailjet visible en
 * WordPress. Mantiene las mismas option keys para no requerir migración de datos.
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

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_menu'], 20);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            FLACSO_Admin_Panel::PAGE_SLUG,
            __('Correos', 'flacso-uruguay'),
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
    }

    public static function sanitize_numeric_id($value): string {
        return preg_replace('/[^0-9]/', '', (string) $value) ?: '';
    }

    public static function get_settings(): array {
        return [
            'api_key' => trim((string) get_option(self::OPTION_API_KEY, '')),
            'secret_key' => trim((string) get_option(self::OPTION_SECRET_KEY, '')),
            'list_id' => trim((string) get_option(self::OPTION_LIST_ID, '')),
            'sender_email' => sanitize_email((string) get_option(self::OPTION_SENDER_EMAIL, get_option('admin_email'))),
            'sender_name' => trim((string) get_option(self::OPTION_SENDER_NAME, wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES))),
            'templates' => [
                'consulta_abierta' => trim((string) get_option(self::OPTION_TEMPLATE_OPEN, '')),
                'consulta_cerrada' => trim((string) get_option(self::OPTION_TEMPLATE_CLOSED, '')),
                'consulta_seminario' => trim((string) get_option(self::OPTION_TEMPLATE_SEMINAR, '')),
            ],
        ];
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
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Correos', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Configuración de Mailjet para correos transaccionales y la lista de difusión. Las consultas de ofertas y seminarios usan esta configuración directamente desde WordPress.', 'flacso-uruguay'); ?></p>

            <?php settings_errors(); ?>

            <div class="notice <?php echo $transactional_ready ? 'notice-success' : 'notice-warning'; ?> inline">
                <p>
                    <strong><?php esc_html_e('Correo transaccional:', 'flacso-uruguay'); ?></strong>
                    <?php echo $transactional_ready ? esc_html__('configurado.', 'flacso-uruguay') : esc_html__('incompleto.', 'flacso-uruguay'); ?>
                    &nbsp;
                    <strong><?php esc_html_e('Lista de difusión:', 'flacso-uruguay'); ?></strong>
                    <?php echo $mailing_ready ? esc_html__('configurada.', 'flacso-uruguay') : esc_html__('incompleta.', 'flacso-uruguay'); ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <h2><?php esc_html_e('Cuenta Mailjet', 'flacso-uruguay'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_API_KEY); ?>"><?php esc_html_e('API Key', 'flacso-uruguay'); ?></label></th>
                        <td><input class="regular-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_API_KEY); ?>" name="<?php echo esc_attr(self::OPTION_API_KEY); ?>" value="<?php echo esc_attr($settings['api_key']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_SECRET_KEY); ?>"><?php esc_html_e('Secret Key', 'flacso-uruguay'); ?></label></th>
                        <td><input class="regular-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_SECRET_KEY); ?>" name="<?php echo esc_attr(self::OPTION_SECRET_KEY); ?>" value="<?php echo esc_attr($settings['secret_key']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_SENDER_EMAIL); ?>"><?php esc_html_e('Remitente', 'flacso-uruguay'); ?></label></th>
                        <td><input class="regular-text" type="email" id="<?php echo esc_attr(self::OPTION_SENDER_EMAIL); ?>" name="<?php echo esc_attr(self::OPTION_SENDER_EMAIL); ?>" value="<?php echo esc_attr($settings['sender_email']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_SENDER_NAME); ?>"><?php esc_html_e('Nombre del remitente', 'flacso-uruguay'); ?></label></th>
                        <td><input class="regular-text" type="text" id="<?php echo esc_attr(self::OPTION_SENDER_NAME); ?>" name="<?php echo esc_attr(self::OPTION_SENDER_NAME); ?>" value="<?php echo esc_attr($settings['sender_name']); ?>"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Plantillas transaccionales', 'flacso-uruguay'); ?></h2>
                <p class="description"><?php esc_html_e('Si un Template ID queda vacío, el flujo directo usa el HTML institucional de respaldo.', 'flacso-uruguay'); ?></p>
                <table class="form-table" role="presentation">
                    <?php
                    $template_rows = [
                        self::OPTION_TEMPLATE_OPEN => ['Consulta con inscripciones abiertas', $settings['templates']['consulta_abierta']],
                        self::OPTION_TEMPLATE_CLOSED => ['Consulta con inscripciones cerradas', $settings['templates']['consulta_cerrada']],
                        self::OPTION_TEMPLATE_SEMINAR => ['Consulta de seminario', $settings['templates']['consulta_seminario']],
                    ];
                    foreach ($template_rows as $option => $row):
                    ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($option); ?>"><?php echo esc_html($row[0]); ?></label></th>
                        <td><input class="regular-text code" type="text" inputmode="numeric" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr($row[1]); ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <h2><?php esc_html_e('Lista de difusión', 'flacso-uruguay'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_LIST_ID); ?>"><?php esc_html_e('Mailjet List ID', 'flacso-uruguay'); ?></label></th>
                        <td>
                            <input class="regular-text code" type="text" inputmode="numeric" id="<?php echo esc_attr(self::OPTION_LIST_ID); ?>" name="<?php echo esc_attr(self::OPTION_LIST_ID); ?>" value="<?php echo esc_attr($settings['list_id']); ?>">
                            <p class="description"><?php esc_html_e('Solo es obligatorio para el formulario de suscripción al mailing.', 'flacso-uruguay'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Guardar correos', 'flacso-uruguay')); ?>
            </form>
        </div>
        <?php
    }
}
