<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Administración de comunicaciones: Mautic es el único canal operativo. */
final class FLACSO_Mail_Settings {
    public const PAGE_SLUG = 'flacso-correos';
    private const SETTINGS_GROUP = 'flacso_correos_group';

    public const OPTION_MAUTIC_ENABLED = 'flacso_mautic_enabled';
    public const OPTION_MAUTIC_BASE_URL = 'flacso_mautic_base_url';
    public const OPTION_MAUTIC_AUTH_TYPE = 'flacso_mautic_auth_type';
    public const OPTION_MAUTIC_USERNAME = 'flacso_mautic_username';
    public const OPTION_MAUTIC_PASSWORD = 'flacso_mautic_password';
    public const OPTION_MAUTIC_TOKEN = 'flacso_mautic_token';
    public const OPTION_MAUTIC_CLIENT_ID = 'flacso_mautic_client_id';
    public const OPTION_MAUTIC_CLIENT_SECRET = 'flacso_mautic_client_secret';

    public const OPTION_MAUTIC_CAMPAIGN_ENABLED = 'flacso_mautic_campaign_enabled';
    public const OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID = 'flacso_mautic_campaign_consultas_id';
    public const OPTION_MAUTIC_ERROR_ALERTS_ENABLED = 'flacso_mautic_error_alerts_enabled';

    public const OPTION_FOLLOWUP_ENABLED = 'flacso_inquiry_followup_enabled';
    public const OPTION_FOLLOWUP_DAYS = 'flacso_inquiry_followup_days';

    private const AJAX_NONCE_ACTION = 'flacso_mail_console_nonce';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_menu'], 20);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('wp_ajax_flacso_mautic_test_connection', [self::class, 'ajax_test_mautic_connection']);
        add_action('wp_ajax_flacso_mautic_test_campaign', [self::class, 'ajax_test_mautic_campaign']);
        add_action('wp_ajax_flacso_mautic_test_contact', [self::class, 'ajax_test_mautic_contact']);
        add_action('wp_ajax_flacso_mautic_test_alert', [self::class, 'ajax_test_mautic_alert']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            FLACSO_Admin_Panel::PAGE_SLUG,
            __('Comunicaciones Mautic', 'flacso-uruguay'),
            __('Correos', 'flacso-uruguay'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function register_settings(): void {
        $settings = [
            self::OPTION_MAUTIC_ENABLED => [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_toggle'],
                'default' => '0',
            ],
            self::OPTION_MAUTIC_BASE_URL => [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_mautic_base_url'],
                'default' => 'https://envios.flacso.edu.uy',
            ],
            self::OPTION_MAUTIC_AUTH_TYPE => [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_auth_type'],
                'default' => 'oauth2',
            ],
            self::OPTION_MAUTIC_USERNAME => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ],
            self::OPTION_MAUTIC_PASSWORD => [
                'type' => 'string',
                'sanitize_callback' => static function ($value): string {
                    return self::sanitize_secret($value, self::OPTION_MAUTIC_PASSWORD);
                },
                'default' => '',
            ],
            self::OPTION_MAUTIC_TOKEN => [
                'type' => 'string',
                'sanitize_callback' => static function ($value): string {
                    return self::sanitize_secret($value, self::OPTION_MAUTIC_TOKEN);
                },
                'default' => '',
            ],
            self::OPTION_MAUTIC_CLIENT_ID => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ],
            self::OPTION_MAUTIC_CLIENT_SECRET => [
                'type' => 'string',
                'sanitize_callback' => static function ($value): string {
                    return self::sanitize_secret($value, self::OPTION_MAUTIC_CLIENT_SECRET);
                },
                'default' => '',
            ],
            self::OPTION_MAUTIC_CAMPAIGN_ENABLED => [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_toggle'],
                'default' => '0',
            ],
            self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID => [
                'type' => 'integer',
                'sanitize_callback' => static fn($v): int => max(0, (int) $v),
                'default' => 0,
            ],
            self::OPTION_MAUTIC_ERROR_ALERTS_ENABLED => [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_toggle'],
                'default' => '1',
            ],
            self::OPTION_FOLLOWUP_ENABLED => [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_toggle'],
                'default' => '0',
            ],
            self::OPTION_FOLLOWUP_DAYS => [
                'type' => 'integer',
                'sanitize_callback' => [self::class, 'sanitize_followup_days'],
                'default' => 5,
            ],
        ];

        if (class_exists('FLACSO_Error_Notifier')) {
            $settings[FLACSO_Error_Notifier::OPTION_TELEGRAM_BOT_TOKEN] = [
                'type' => 'string',
                'sanitize_callback' => static function ($value): string {
                    return self::sanitize_secret($value, FLACSO_Error_Notifier::OPTION_TELEGRAM_BOT_TOKEN);
                },
                'default' => '',
            ];
            $settings[FLACSO_Error_Notifier::OPTION_TELEGRAM_CHAT_ID] = [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ];
            $settings[FLACSO_Error_Notifier::OPTION_EMAIL] = [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default' => '',
            ];
        }

        foreach ($settings as $option => $args) {
            register_setting(self::SETTINGS_GROUP, $option, $args);
        }
    }

    public static function sanitize_toggle($value): string {
        return !empty($value) && (string) $value !== '0' ? '1' : '0';
    }

    public static function sanitize_auth_type($value): string {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['oauth2', 'basic', 'bearer'], true) ? $value : 'oauth2';
    }

    public static function sanitize_mautic_base_url($value): string {
        return rtrim(esc_url_raw(trim((string) $value)), '/');
    }

    public static function sanitize_followup_days($value): int {
        return max(1, min(60, (int) $value));
    }

    private static function sanitize_secret($value, string $option): string {
        $value = trim((string) $value);
        if ($value === '') {
            return (string) get_option($option, '');
        }

        return sanitize_text_field($value);
    }

    public static function get_mautic_campaign_settings(): array {
        return [
            'enabled' => (string) get_option(self::OPTION_MAUTIC_CAMPAIGN_ENABLED, '0') === '1',
            'consultas_id' => max(0, (int) get_option(self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID, 0)),
        ];
    }

    public static function get_followup_settings(): array {
        return [
            'enabled' => (string) get_option(self::OPTION_FOLLOWUP_ENABLED, '0') === '1',
            'days' => self::sanitize_followup_days(get_option(self::OPTION_FOLLOWUP_DAYS, 5)),
        ];
    }

    public static function error_alerts_enabled(): bool {
        return (string) get_option(self::OPTION_MAUTIC_ERROR_ALERTS_ENABLED, '1') === '1';
    }

    public static function get_settings(): array {
        return [
            'mautic' => class_exists('FLACSO_Mautic_Client') ? FLACSO_Mautic_Client::get_settings() : [],
            'mautic_campaign' => self::get_mautic_campaign_settings(),
            'followup' => self::get_followup_settings(),
            'error_alerts_enabled' => self::error_alerts_enabled(),
        ];
    }

    public static function get_offer_inquiry_engine_status(): array {
        $ready = class_exists('FLACSO_Mautic_Client') && FLACSO_Mautic_Client::is_configured();

        return [
            'engine' => 'mautic',
            'is_mautic_primary' => true,
            'mautic_ready' => $ready,
            'status_label' => $ready ? 'Mautic activo' : 'Configuración requerida',
        ];
    }

    private static function ajax_guard(): void {
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos'], 403);
        }
    }

    private static function send_test_result(array $result): void {
        $payload = [
            'message' => (string) ($result['message'] ?? 'Prueba finalizada.'),
            'code' => (int) ($result['code'] ?? 0),
        ];

        if (!empty($result['ok'])) {
            wp_send_json_success($payload);
        }

        wp_send_json_error($payload);
    }

    public static function ajax_test_mautic_connection(): void {
        self::ajax_guard();

        $result = class_exists('FLACSO_Mautic_Client')
            ? FLACSO_Mautic_Client::test_connection()
            : ['ok' => false, 'message' => 'Mautic no disponible.'];

        self::send_test_result($result);
    }

    public static function ajax_test_mautic_campaign(): void {
        self::ajax_guard();

        $campaign = self::get_mautic_campaign_settings();
        $result = class_exists('FLACSO_Mautic_Client')
            ? FLACSO_Mautic_Client::test_campaign((int) $campaign['consultas_id'])
            : ['ok' => false, 'message' => 'Mautic no disponible.'];

        self::send_test_result($result);
    }

    public static function ajax_test_mautic_contact(): void {
        self::ajax_guard();

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $result = class_exists('FLACSO_Mautic_Client')
            ? FLACSO_Mautic_Client::test_contact_search($email)
            : ['ok' => false, 'message' => 'Mautic no disponible.'];

        self::send_test_result($result);
    }

    public static function ajax_test_mautic_alert(): void {
        self::ajax_guard();

        if (!class_exists('FLACSO_Error_Notifier')) {
            self::send_test_result(['ok' => false, 'message' => 'El notificador de errores no está disponible.']);
        }

        $sent = method_exists('FLACSO_Error_Notifier', 'test_notification')
            ? FLACSO_Error_Notifier::test_notification('Mautic · FLACSO > Correos')
            : FLACSO_Error_Notifier::report(
                'Prueba manual de alertas Mautic desde FLACSO > Correos.',
                __FILE__,
                __LINE__,
                'mautic_test'
            );

        self::send_test_result([
            'ok' => $sent,
            'message' => $sent
                ? 'Alerta de prueba enviada. Revisa Telegram o el correo de respaldo.'
                : 'No se pudo enviar la alerta. Revisa Bot Token, Chat ID o correo de respaldo.',
        ]);
    }

    private static function secret_hint(string $option): string {
        return trim((string) get_option($option, '')) !== ''
            ? __('Configurado. Déjalo vacío para conservarlo.', 'flacso-uruguay')
            : __('Todavía no configurado.', 'flacso-uruguay');
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $mautic = class_exists('FLACSO_Mautic_Client') ? FLACSO_Mautic_Client::get_settings() : [];
        $campaign = self::get_mautic_campaign_settings();
        $auth_type = (string) ($mautic['auth_type'] ?? 'oauth2');
        $ready = class_exists('FLACSO_Mautic_Client') && FLACSO_Mautic_Client::is_configured();
        $nonce = wp_create_nonce(self::AJAX_NONCE_ACTION);

        $telegram_token_option = class_exists('FLACSO_Error_Notifier') ? FLACSO_Error_Notifier::OPTION_TELEGRAM_BOT_TOKEN : '';
        $telegram_chat_option = class_exists('FLACSO_Error_Notifier') ? FLACSO_Error_Notifier::OPTION_TELEGRAM_CHAT_ID : '';
        $alert_email_option = class_exists('FLACSO_Error_Notifier') ? FLACSO_Error_Notifier::OPTION_EMAIL : '';

        $telegram_chat = $telegram_chat_option !== '' ? (string) get_option($telegram_chat_option, '') : '';
        $alert_email = $alert_email_option !== '' ? (string) get_option($alert_email_option, '') : '';
        ?>
        <div class="wrap flacso-mautic-admin">
            <div class="flacso-mautic-heading">
                <div>
                    <h1><?php esc_html_e('Comunicaciones Mautic', 'flacso-uruguay'); ?></h1>
                    <p><?php esc_html_e('WordPress captura la consulta y sincroniza con Mautic. La automatización, campañas y envíos quedan del lado de Mautic.', 'flacso-uruguay'); ?></p>
                </div>
                <span class="flacso-status <?php echo $ready ? 'is-ok' : 'is-warning'; ?>">
                    <?php echo esc_html($ready ? __('Configurado', 'flacso-uruguay') : __('Requiere configuración', 'flacso-uruguay')); ?>
                </span>
            </div>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <div class="flacso-settings-grid">
                    <section class="flacso-settings-card">
                        <div class="flacso-card-head">
                            <div>
                                <h2><?php esc_html_e('Conexión API', 'flacso-uruguay'); ?></h2>
                                <p><?php esc_html_e('OAuth2 Client Credentials es la opción recomendada para esta integración servidor a servidor.', 'flacso-uruguay'); ?></p>
                            </div>
                        </div>

                        <label class="flacso-toggle">
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_MAUTIC_ENABLED); ?>" value="0">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_MAUTIC_ENABLED); ?>" value="1" <?php checked($mautic['enabled'] ?? false); ?>>
                            <span>
                                <strong><?php esc_html_e('Habilitar integración con Mautic', 'flacso-uruguay'); ?></strong>
                                <small><?php esc_html_e('Permite sincronizar contactos y ejecutar las operaciones configuradas.', 'flacso-uruguay'); ?></small>
                            </span>
                        </label>

                        <div class="flacso-field">
                            <label for="<?php echo esc_attr(self::OPTION_MAUTIC_BASE_URL); ?>"><?php esc_html_e('URL de Mautic', 'flacso-uruguay'); ?></label>
                            <input class="large-text code" type="url" id="<?php echo esc_attr(self::OPTION_MAUTIC_BASE_URL); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_BASE_URL); ?>" value="<?php echo esc_attr($mautic['base_url'] ?? 'https://envios.flacso.edu.uy'); ?>">
                        </div>

                        <div class="flacso-field">
                            <label for="<?php echo esc_attr(self::OPTION_MAUTIC_AUTH_TYPE); ?>"><?php esc_html_e('Autenticación', 'flacso-uruguay'); ?></label>
                            <select id="<?php echo esc_attr(self::OPTION_MAUTIC_AUTH_TYPE); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_AUTH_TYPE); ?>">
                                <option value="oauth2" <?php selected($auth_type, 'oauth2'); ?>>OAuth2 · Client Credentials</option>
                                <option value="basic" <?php selected($auth_type, 'basic'); ?>>Basic · Usuario y contraseña</option>
                                <option value="bearer" <?php selected($auth_type, 'bearer'); ?>>Bearer · Token manual</option>
                            </select>
                        </div>

                        <div class="flacso-auth-panel" data-auth-panel="oauth2">
                            <div class="flacso-credentials-grid">
                                <div class="flacso-field">
                                    <label for="<?php echo esc_attr(self::OPTION_MAUTIC_CLIENT_ID); ?>"><?php esc_html_e('Clave Pública (Client ID)', 'flacso-uruguay'); ?></label>
                                    <input class="large-text code" type="text" autocomplete="off" id="<?php echo esc_attr(self::OPTION_MAUTIC_CLIENT_ID); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_CLIENT_ID); ?>" value="<?php echo esc_attr($mautic['client_id'] ?? ''); ?>">
                                </div>
                                <div class="flacso-field">
                                    <label for="<?php echo esc_attr(self::OPTION_MAUTIC_CLIENT_SECRET); ?>"><?php esc_html_e('Clave Secreta (Client Secret)', 'flacso-uruguay'); ?></label>
                                    <input class="large-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_MAUTIC_CLIENT_SECRET); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_CLIENT_SECRET); ?>" value="" placeholder="••••••••••••">
                                    <p class="description"><?php echo esc_html(self::secret_hint(self::OPTION_MAUTIC_CLIENT_SECRET)); ?></p>
                                </div>
                            </div>
                            <p class="description"><?php esc_html_e('Estas credenciales se crean en Mautic > Configuración > Credenciales API usando Client Credentials.', 'flacso-uruguay'); ?></p>
                        </div>

                        <div class="flacso-auth-panel" data-auth-panel="basic">
                            <div class="flacso-credentials-grid">
                                <div class="flacso-field">
                                    <label for="<?php echo esc_attr(self::OPTION_MAUTIC_USERNAME); ?>"><?php esc_html_e('Usuario', 'flacso-uruguay'); ?></label>
                                    <input class="large-text" type="text" autocomplete="username" id="<?php echo esc_attr(self::OPTION_MAUTIC_USERNAME); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_USERNAME); ?>" value="<?php echo esc_attr($mautic['username'] ?? ''); ?>">
                                </div>
                                <div class="flacso-field">
                                    <label for="<?php echo esc_attr(self::OPTION_MAUTIC_PASSWORD); ?>"><?php esc_html_e('Contraseña', 'flacso-uruguay'); ?></label>
                                    <input class="large-text" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_MAUTIC_PASSWORD); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_PASSWORD); ?>" value="" placeholder="••••••••••••">
                                    <p class="description"><?php echo esc_html(self::secret_hint(self::OPTION_MAUTIC_PASSWORD)); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="flacso-auth-panel" data-auth-panel="bearer">
                            <div class="flacso-field">
                                <label for="<?php echo esc_attr(self::OPTION_MAUTIC_TOKEN); ?>"><?php esc_html_e('Token Bearer', 'flacso-uruguay'); ?></label>
                                <input class="large-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_MAUTIC_TOKEN); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_TOKEN); ?>" value="" placeholder="••••••••••••">
                                <p class="description"><?php echo esc_html(self::secret_hint(self::OPTION_MAUTIC_TOKEN)); ?></p>
                            </div>
                        </div>
                    </section>

                    <section class="flacso-settings-card">
                        <div class="flacso-card-head">
                            <div>
                                <h2><?php esc_html_e('Campaña de consultas', 'flacso-uruguay'); ?></h2>
                                <p><?php esc_html_e('Controla la incorporación automática de contactos con consentimiento.', 'flacso-uruguay'); ?></p>
                            </div>
                        </div>

                        <label class="flacso-toggle">
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_ENABLED); ?>" value="0">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_ENABLED); ?>" value="1" <?php checked($campaign['enabled']); ?>>
                            <span>
                                <strong><?php esc_html_e('Incorporar contactos automáticamente', 'flacso-uruguay'); ?></strong>
                                <small><?php esc_html_e('Sólo cuando el flujo tiene consentimiento de marketing válido.', 'flacso-uruguay'); ?></small>
                            </span>
                        </label>

                        <div class="flacso-field flacso-small-field">
                            <label for="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID); ?>"><?php esc_html_e('ID de campaña', 'flacso-uruguay'); ?></label>
                            <input type="number" min="1" id="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID); ?>" value="<?php echo esc_attr((string) $campaign['consultas_id']); ?>">
                        </div>
                    </section>

                    <section class="flacso-settings-card">
                        <div class="flacso-card-head">
                            <div>
                                <h2><?php esc_html_e('Alertas de errores', 'flacso-uruguay'); ?></h2>
                                <p><?php esc_html_e('Telegram es el canal principal. Si falla o no está configurado, se utiliza el correo de respaldo.', 'flacso-uruguay'); ?></p>
                            </div>
                        </div>

                        <label class="flacso-toggle">
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_MAUTIC_ERROR_ALERTS_ENABLED); ?>" value="0">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_MAUTIC_ERROR_ALERTS_ENABLED); ?>" value="1" <?php checked(self::error_alerts_enabled()); ?>>
                            <span>
                                <strong><?php esc_html_e('Notificar errores operativos de Mautic', 'flacso-uruguay'); ?></strong>
                                <small><?php esc_html_e('Las alertas repetidas se deduplican para evitar spam.', 'flacso-uruguay'); ?></small>
                            </span>
                        </label>

                        <?php if ($telegram_token_option !== ''): ?>
                            <div class="flacso-credentials-grid">
                                <div class="flacso-field">
                                    <label for="<?php echo esc_attr($telegram_token_option); ?>"><?php esc_html_e('Telegram Bot Token', 'flacso-uruguay'); ?></label>
                                    <input class="large-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr($telegram_token_option); ?>" name="<?php echo esc_attr($telegram_token_option); ?>" value="" placeholder="123456:ABC…">
                                    <p class="description"><?php echo esc_html(self::secret_hint($telegram_token_option)); ?></p>
                                </div>
                                <div class="flacso-field">
                                    <label for="<?php echo esc_attr($telegram_chat_option); ?>"><?php esc_html_e('Telegram Chat ID', 'flacso-uruguay'); ?></label>
                                    <input class="large-text code" type="text" id="<?php echo esc_attr($telegram_chat_option); ?>" name="<?php echo esc_attr($telegram_chat_option); ?>" value="<?php echo esc_attr($telegram_chat); ?>" placeholder="-1001234567890">
                                </div>
                            </div>
                            <div class="flacso-field">
                                <label for="<?php echo esc_attr($alert_email_option); ?>"><?php esc_html_e('Correo de respaldo', 'flacso-uruguay'); ?></label>
                                <input class="large-text" type="email" id="<?php echo esc_attr($alert_email_option); ?>" name="<?php echo esc_attr($alert_email_option); ?>" value="<?php echo esc_attr($alert_email); ?>">
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="flacso-settings-card flacso-test-card">
                        <div class="flacso-card-head">
                            <div>
                                <h2><?php esc_html_e('Pruebas y diagnóstico', 'flacso-uruguay'); ?></h2>
                                <p><?php esc_html_e('Las pruebas usan la configuración ya guardada y no envían correos a contactos.', 'flacso-uruguay'); ?></p>
                            </div>
                        </div>

                        <div class="flacso-test-actions">
                            <button type="button" class="button button-secondary flacso-mautic-test" data-test="connection"><?php esc_html_e('Probar conexión', 'flacso-uruguay'); ?></button>
                            <button type="button" class="button button-secondary flacso-mautic-test" data-test="campaign"><?php esc_html_e('Probar campaña', 'flacso-uruguay'); ?></button>
                            <button type="button" class="button button-secondary flacso-mautic-test" data-test="alert"><?php esc_html_e('Probar alerta Telegram', 'flacso-uruguay'); ?></button>
                        </div>

                        <div class="flacso-contact-test">
                            <input type="email" id="flacso-mautic-test-email" class="regular-text" placeholder="correo@ejemplo.com">
                            <button type="button" class="button button-secondary flacso-mautic-test" data-test="contact"><?php esc_html_e('Buscar contacto', 'flacso-uruguay'); ?></button>
                        </div>

                        <div id="flacso-mautic-test-result" class="flacso-test-result" hidden></div>
                    </section>
                </div>

                <div class="flacso-save-bar">
                    <?php submit_button(__('Guardar configuración Mautic', 'flacso-uruguay'), 'primary', 'submit', false); ?>
                    <span><?php esc_html_e('Los secretos vacíos se conservan; no se muestran nuevamente en pantalla.', 'flacso-uruguay'); ?></span>
                </div>
            </form>
        </div>

        <style>
            .flacso-mautic-admin{max-width:1180px}
            .flacso-mautic-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin:18px 0 20px}
            .flacso-mautic-heading h1{margin:0 0 6px}
            .flacso-mautic-heading p{margin:0;color:#50575e;max-width:780px;font-size:14px}
            .flacso-status{display:inline-flex;align-items:center;border-radius:999px;padding:7px 11px;font-weight:600;white-space:nowrap}
            .flacso-status.is-ok{background:#edfaef;color:#116329;border:1px solid #b8e6c3}
            .flacso-status.is-warning{background:#fff8e5;color:#7a4b00;border:1px solid #f0d48a}
            .flacso-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start}
            .flacso-settings-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;box-shadow:0 1px 1px rgba(0,0,0,.03)}
            .flacso-card-head{padding-bottom:14px;margin-bottom:16px;border-bottom:1px solid #f0f0f1}
            .flacso-card-head h2{font-size:17px;margin:0 0 5px}
            .flacso-card-head p{margin:0;color:#646970;line-height:1.45}
            .flacso-field{margin:0 0 16px}
            .flacso-field:last-child{margin-bottom:0}
            .flacso-field>label{display:block;font-weight:600;margin-bottom:6px}
            .flacso-field input[type=text],.flacso-field input[type=password],.flacso-field input[type=url],.flacso-field input[type=email],.flacso-field select{width:100%;max-width:none}
            .flacso-small-field input{max-width:140px!important}
            .flacso-credentials-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
            .flacso-toggle{display:flex;align-items:flex-start;gap:10px;padding:12px;border:1px solid #dcdcde;border-radius:6px;margin-bottom:16px;background:#f9f9f9}
            .flacso-toggle input[type=checkbox]{margin-top:3px}
            .flacso-toggle span{display:flex;flex-direction:column;gap:3px}
            .flacso-toggle small{color:#646970;font-weight:400;line-height:1.35}
            .flacso-auth-panel[hidden]{display:none!important}
            .flacso-test-actions{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
            .flacso-contact-test{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
            .flacso-contact-test input{flex:1;min-width:220px}
            .flacso-test-result{margin-top:14px;padding:10px 12px;border-radius:6px;border-left:4px solid #2271b1;background:#f0f6fc}
            .flacso-test-result.is-ok{border-left-color:#00a32a;background:#edfaef}
            .flacso-test-result.is-error{border-left-color:#d63638;background:#fcf0f1}
            .flacso-save-bar{position:sticky;bottom:0;display:flex;align-items:center;gap:14px;margin-top:16px;padding:12px 16px;background:rgba(240,240,241,.96);border:1px solid #dcdcde;border-radius:8px;backdrop-filter:blur(4px)}
            .flacso-save-bar span{color:#646970}
            @media (max-width:900px){
                .flacso-settings-grid{grid-template-columns:1fr}
                .flacso-credentials-grid{grid-template-columns:1fr}
                .flacso-mautic-heading{flex-direction:column}
                .flacso-save-bar{align-items:flex-start;flex-direction:column}
            }
        </style>

        <script>
        (function(){
            const authSelect = document.getElementById(<?php echo wp_json_encode(self::OPTION_MAUTIC_AUTH_TYPE); ?>);
            const panels = Array.from(document.querySelectorAll('[data-auth-panel]'));
            const result = document.getElementById('flacso-mautic-test-result');
            const nonce = <?php echo wp_json_encode($nonce); ?>;

            function syncAuthPanels(){
                const value = authSelect ? authSelect.value : 'oauth2';
                panels.forEach(function(panel){
                    panel.hidden = panel.getAttribute('data-auth-panel') !== value;
                });
            }

            function showResult(ok, message, code){
                result.hidden = false;
                result.className = 'flacso-test-result ' + (ok ? 'is-ok' : 'is-error');
                result.textContent = (code ? 'HTTP ' + code + ' · ' : '') + message;
            }

            function runTest(button){
                const kind = button.getAttribute('data-test');
                const actions = {
                    connection: 'flacso_mautic_test_connection',
                    campaign: 'flacso_mautic_test_campaign',
                    contact: 'flacso_mautic_test_contact',
                    alert: 'flacso_mautic_test_alert'
                };
                const body = new URLSearchParams();
                body.set('action', actions[kind]);
                body.set('nonce', nonce);

                if(kind === 'contact'){
                    const email = document.getElementById('flacso-mautic-test-email').value.trim();
                    body.set('email', email);
                }

                button.disabled = true;
                result.hidden = false;
                result.className = 'flacso-test-result';
                result.textContent = 'Ejecutando prueba…';

                fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: body.toString()
                })
                .then(function(response){ return response.json(); })
                .then(function(data){
                    const payload = data && data.data ? data.data : {};
                    showResult(Boolean(data && data.success), payload.message || 'Respuesta sin detalle.', payload.code || 0);
                })
                .catch(function(error){
                    showResult(false, error && error.message ? error.message : 'No se pudo ejecutar la prueba.', 0);
                })
                .finally(function(){ button.disabled = false; });
            }

            if(authSelect){
                authSelect.addEventListener('change', syncAuthPanels);
                syncAuthPanels();
            }

            document.querySelectorAll('.flacso-mautic-test').forEach(function(button){
                button.addEventListener('click', function(){ runTest(button); });
            });
        })();
        </script>
        <?php
    }
}
