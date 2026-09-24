<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuración técnica y diagnóstico operativo.
 *
 * Mantiene únicamente dependencias externas que no pertenecen a un módulo de
 * contenido concreto. No expone el endpoint de consultas de ofertas ni la URL
 * del Editor: esos flujos ya son internos al plugin.
 */
final class FLACSO_System_Settings {
    public const PAGE_SLUG = 'flacso-sistema';
    private const SETTINGS_GROUP = 'flacso_system_group';

    private const OPTION_CONSULTAS_WEBHOOK_URL = 'fc_consultas_webhook_url';
    private const OPTION_WEBHOOK_TOKEN = 'flacso_webhook_token';
    private const OPTION_CHARLAS_WEBHOOK_URL = 'flacso_charlas_abiertas_webhook_url';
    private const OPTION_PREINSCRIPCIONES_WEBHOOK_URL = 'flacso_preinscripciones_webhook_url';
    private const OPTION_USE_TELEGRAM = 'fc_use_telegram';
    private const OPTION_TELEGRAM_BOT_TOKEN = 'fc_telegram_bot_token';
    private const OPTION_TELEGRAM_CHAT_ID = 'fc_telegram_chat_id';
    private const OPTION_USE_RECAPTCHA = 'fc_use_recaptcha';
    private const OPTION_RECAPTCHA_SITE_KEY = 'fc_recaptcha_site_key';
    private const OPTION_RECAPTCHA_SECRET_KEY = 'fc_recaptcha_secret_key';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_menu'], 30);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            FLACSO_Admin_Panel::PAGE_SLUG,
            __('Sistema', 'flacso-uruguay'),
            __('Sistema', 'flacso-uruguay'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function register_settings(): void {
        register_setting(self::SETTINGS_GROUP, self::OPTION_CONSULTAS_WEBHOOK_URL, [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_WEBHOOK_TOKEN, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_CHARLAS_WEBHOOK_URL, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_charlas_webhook_url'],
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL, [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_USE_TELEGRAM, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_toggle'],
            'default' => '0',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_TELEGRAM_BOT_TOKEN, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_TELEGRAM_CHAT_ID, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_USE_RECAPTCHA, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_toggle'],
            'default' => '0',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_RECAPTCHA_SITE_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting(self::SETTINGS_GROUP, self::OPTION_RECAPTCHA_SECRET_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
    }

    public static function sanitize_toggle($value): string {
        return (string) $value === '1' ? '1' : '0';
    }

    public static function sanitize_charlas_webhook_url($value): string {
        if (function_exists('flacso_charlas_abiertas_sanitize_webhook_url')) {
            return (string) flacso_charlas_abiertas_sanitize_webhook_url($value);
        }

        return (string) esc_url_raw($value);
    }

    public static function get_page_url(array $args = []): string {
        return add_query_arg(
            array_merge(['page' => self::PAGE_SLUG], $args),
            admin_url('admin.php')
        );
    }

    private static function pg_is_configured(): bool {
        if (class_exists('FLACSO_DB') && method_exists('FLACSO_DB', 'is_configured')) {
            return FLACSO_DB::is_configured();
        }

        foreach (['FLACSO_PG_HOST', 'FLACSO_PG_PORT', 'FLACSO_PG_DATABASE', 'FLACSO_PG_USER', 'FLACSO_PG_PASSWORD'] as $constant) {
            if (!defined($constant) || trim((string) constant($constant)) === '') {
                return false;
            }
        }

        return true;
    }

    private static function status_row(string $label, bool $ready, string $detail = ''): void {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <strong style="color:<?php echo esc_attr($ready ? '#166534' : '#991b1b'); ?>">
                    <?php echo esc_html($ready ? __('OK', 'flacso-uruguay') : __('Pendiente', 'flacso-uruguay')); ?>
                </strong>
                <?php if ($detail !== ''): ?>
                    <p class="description"><?php echo esc_html($detail); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $pg_ready = self::pg_is_configured();
        $pdo_ready = extension_loaded('pdo_pgsql');
        $mail_ready = class_exists('FLACSO_Mail_Settings')
            ? FLACSO_Mail_Settings::is_transactional_ready()
            : false;

        $consultas_url = trim((string) get_option(self::OPTION_CONSULTAS_WEBHOOK_URL, ''));
        $charlas_url = trim((string) get_option(self::OPTION_CHARLAS_WEBHOOK_URL, ''));
        $preinscripciones_url = trim((string) get_option(self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL, ''));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sistema', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Diagnóstico técnico y conexiones que aún dependen de servicios externos. Las consultas de ofertas y seminarios no se configuran aquí: se procesan directamente en WordPress.', 'flacso-uruguay'); ?></p>

            <?php settings_errors(); ?>

            <h2><?php esc_html_e('Estado', 'flacso-uruguay'); ?></h2>
            <table class="widefat striped" style="max-width:900px">
                <tbody>
                    <?php self::status_row(__('PostgreSQL configurado', 'flacso-uruguay'), $pg_ready, __('Se usan las constantes FLACSO_PG_* del servidor.', 'flacso-uruguay')); ?>
                    <?php self::status_row(__('Extensión pdo_pgsql', 'flacso-uruguay'), $pdo_ready); ?>
                    <?php self::status_row(__('Mailjet transaccional', 'flacso-uruguay'), $mail_ready, __('La configuración se administra en FLACSO > Correos.', 'flacso-uruguay')); ?>
                    <?php self::status_row(__('Consultas generales', 'flacso-uruguay'), $consultas_url !== '', $consultas_url); ?>
                    <?php self::status_row(__('Charlas abiertas', 'flacso-uruguay'), $charlas_url !== '', $charlas_url); ?>
                    <?php self::status_row(__('Preinscripciones', 'flacso-uruguay'), $preinscripciones_url !== '', $preinscripciones_url); ?>
                </tbody>
            </table>

            <form method="post" action="options.php" style="margin-top:24px">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <h2><?php esc_html_e('Servicios todavía externos', 'flacso-uruguay'); ?></h2>
                <p class="description"><?php esc_html_e('Solo se muestran flujos que todavía requieren un endpoint externo. El webhook de ofertas fue retirado.', 'flacso-uruguay'); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_CONSULTAS_WEBHOOK_URL); ?>"><?php esc_html_e('Consultas generales', 'flacso-uruguay'); ?></label></th>
                        <td><input class="large-text code" type="url" id="<?php echo esc_attr(self::OPTION_CONSULTAS_WEBHOOK_URL); ?>" name="<?php echo esc_attr(self::OPTION_CONSULTAS_WEBHOOK_URL); ?>" value="<?php echo esc_attr($consultas_url); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_CHARLAS_WEBHOOK_URL); ?>"><?php esc_html_e('Charlas abiertas', 'flacso-uruguay'); ?></label></th>
                        <td><input class="large-text code" type="url" id="<?php echo esc_attr(self::OPTION_CHARLAS_WEBHOOK_URL); ?>" name="<?php echo esc_attr(self::OPTION_CHARLAS_WEBHOOK_URL); ?>" value="<?php echo esc_attr($charlas_url); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL); ?>"><?php esc_html_e('Preinscripciones', 'flacso-uruguay'); ?></label></th>
                        <td><input class="large-text code" type="url" id="<?php echo esc_attr(self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL); ?>" name="<?php echo esc_attr(self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL); ?>" value="<?php echo esc_attr($preinscripciones_url); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_WEBHOOK_TOKEN); ?>"><?php esc_html_e('Token compartido', 'flacso-uruguay'); ?></label></th>
                        <td><input class="regular-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_WEBHOOK_TOKEN); ?>" name="<?php echo esc_attr(self::OPTION_WEBHOOK_TOKEN); ?>" value="<?php echo esc_attr((string) get_option(self::OPTION_WEBHOOK_TOKEN, '')); ?>"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Formularios y notificaciones', 'flacso-uruguay'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Telegram', 'flacso-uruguay'); ?></th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_USE_TELEGRAM); ?>" value="0">
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_USE_TELEGRAM); ?>" value="1" <?php checked(get_option(self::OPTION_USE_TELEGRAM, '0'), '1'); ?>>
                                <?php esc_html_e('Activar notificaciones Telegram del formulario general', 'flacso-uruguay'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_TELEGRAM_BOT_TOKEN); ?>"><?php esc_html_e('Telegram Bot Token', 'flacso-uruguay'); ?></label></th>
                        <td><input class="regular-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_TELEGRAM_BOT_TOKEN); ?>" name="<?php echo esc_attr(self::OPTION_TELEGRAM_BOT_TOKEN); ?>" value="<?php echo esc_attr((string) get_option(self::OPTION_TELEGRAM_BOT_TOKEN, '')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_TELEGRAM_CHAT_ID); ?>"><?php esc_html_e('Telegram Chat ID', 'flacso-uruguay'); ?></label></th>
                        <td><input class="regular-text code" type="text" id="<?php echo esc_attr(self::OPTION_TELEGRAM_CHAT_ID); ?>" name="<?php echo esc_attr(self::OPTION_TELEGRAM_CHAT_ID); ?>" value="<?php echo esc_attr((string) get_option(self::OPTION_TELEGRAM_CHAT_ID, '')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('reCAPTCHA', 'flacso-uruguay'); ?></th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_USE_RECAPTCHA); ?>" value="0">
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_USE_RECAPTCHA); ?>" value="1" <?php checked(get_option(self::OPTION_USE_RECAPTCHA, '0'), '1'); ?>>
                                <?php esc_html_e('Proteger el formulario general con reCAPTCHA v3', 'flacso-uruguay'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_RECAPTCHA_SITE_KEY); ?>"><?php esc_html_e('reCAPTCHA Site Key', 'flacso-uruguay'); ?></label></th>
                        <td><input class="regular-text code" type="text" id="<?php echo esc_attr(self::OPTION_RECAPTCHA_SITE_KEY); ?>" name="<?php echo esc_attr(self::OPTION_RECAPTCHA_SITE_KEY); ?>" value="<?php echo esc_attr((string) get_option(self::OPTION_RECAPTCHA_SITE_KEY, '')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_RECAPTCHA_SECRET_KEY); ?>"><?php esc_html_e('reCAPTCHA Secret Key', 'flacso-uruguay'); ?></label></th>
                        <td><input class="regular-text code" type="password" autocomplete="new-password" id="<?php echo esc_attr(self::OPTION_RECAPTCHA_SECRET_KEY); ?>" name="<?php echo esc_attr(self::OPTION_RECAPTCHA_SECRET_KEY); ?>" value="<?php echo esc_attr((string) get_option(self::OPTION_RECAPTCHA_SECRET_KEY, '')); ?>"></td>
                    </tr>
                </table>

                <?php submit_button(__('Guardar sistema', 'flacso-uruguay')); ?>
            </form>
        </div>
        <?php
    }
}

FLACSO_System_Settings::init();
