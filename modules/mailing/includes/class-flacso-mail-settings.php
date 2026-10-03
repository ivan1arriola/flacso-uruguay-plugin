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
    public const OPTION_MAUTIC_CAMPAIGN_ENABLED = 'flacso_mautic_campaign_enabled';
    public const OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID = 'flacso_mautic_campaign_consultas_id';
    public const OPTION_FOLLOWUP_ENABLED = 'flacso_inquiry_followup_enabled';
    public const OPTION_FOLLOWUP_DAYS = 'flacso_inquiry_followup_days';

    public static function init(): void {
        if (!is_admin()) return;
        add_action('admin_menu', [self::class, 'register_menu'], 20);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('wp_ajax_flacso_mautic_test_connection', [self::class, 'ajax_test_mautic_connection']);
    }

    public static function register_menu(): void {
        add_submenu_page(FLACSO_Admin_Panel::PAGE_SLUG, __('Comunicaciones Mautic', 'flacso-uruguay'), __('Correos', 'flacso-uruguay'), 'manage_options', self::PAGE_SLUG, [self::class, 'render_page']);
    }

    public static function register_settings(): void {
        $settings = [
            self::OPTION_MAUTIC_ENABLED => ['type' => 'string', 'sanitize_callback' => static fn($v): string => !empty($v) && $v !== '0' ? '1' : '0', 'default' => '0'],
            self::OPTION_MAUTIC_BASE_URL => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_mautic_base_url'], 'default' => 'https://envios.flacso.edu.uy'],
            self::OPTION_MAUTIC_AUTH_TYPE => ['type' => 'string', 'sanitize_callback' => static fn($v): string => in_array(strtolower(trim((string) $v)), ['basic', 'bearer'], true) ? strtolower(trim((string) $v)) : 'basic', 'default' => 'basic'],
            self::OPTION_MAUTIC_USERNAME => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            self::OPTION_MAUTIC_PASSWORD => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            self::OPTION_MAUTIC_TOKEN => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            self::OPTION_MAUTIC_CAMPAIGN_ENABLED => ['type' => 'string', 'sanitize_callback' => static fn($v): string => !empty($v) && $v !== '0' ? '1' : '0', 'default' => '0'],
            self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID => ['type' => 'integer', 'sanitize_callback' => static fn($v): int => max(0, (int) $v), 'default' => 0],
            self::OPTION_FOLLOWUP_ENABLED => ['type' => 'string', 'sanitize_callback' => static fn($v): string => !empty($v) && $v !== '0' ? '1' : '0', 'default' => '0'],
            self::OPTION_FOLLOWUP_DAYS => ['type' => 'integer', 'sanitize_callback' => [self::class, 'sanitize_followup_days'], 'default' => 5],
        ];
        foreach ($settings as $option => $args) register_setting(self::SETTINGS_GROUP, $option, $args);
    }

    public static function sanitize_mautic_base_url($value): string { return rtrim(esc_url_raw(trim((string) $value)), '/'); }
    public static function sanitize_followup_days($value): int { return max(1, min(60, (int) $value)); }

    public static function get_mautic_campaign_settings(): array {
        return ['enabled' => (string) get_option(self::OPTION_MAUTIC_CAMPAIGN_ENABLED, '0') === '1', 'consultas_id' => max(0, (int) get_option(self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID, 0))];
    }

    public static function get_followup_settings(): array {
        return ['enabled' => (string) get_option(self::OPTION_FOLLOWUP_ENABLED, '0') === '1', 'days' => self::sanitize_followup_days(get_option(self::OPTION_FOLLOWUP_DAYS, 5))];
    }

    public static function get_settings(): array {
        return ['mautic' => class_exists('FLACSO_Mautic_Client') ? FLACSO_Mautic_Client::get_settings() : [], 'mautic_campaign' => self::get_mautic_campaign_settings(), 'followup' => self::get_followup_settings()];
    }

    public static function get_offer_inquiry_engine_status(): array {
        $ready = class_exists('FLACSO_Mautic_Client') && FLACSO_Mautic_Client::is_configured();
        return ['engine' => 'mautic', 'is_mautic_primary' => true, 'mautic_ready' => $ready, 'status_label' => $ready ? 'Mautic activo' : 'Configuración requerida'];
    }

    public static function ajax_test_mautic_connection(): void {
        check_ajax_referer('flacso_mail_console_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Sin permisos'], 403);
        $result = class_exists('FLACSO_Mautic_Client') ? FLACSO_Mautic_Client::test_connection() : ['ok' => false, 'message' => 'Mautic no disponible.'];
        if (!empty($result['ok'])) wp_send_json_success(['message' => $result['message']]);
        wp_send_json_error(['message' => $result['message'] ?? 'No se pudo conectar con Mautic.']);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) return;
        $mautic = class_exists('FLACSO_Mautic_Client') ? FLACSO_Mautic_Client::get_settings() : [];
        $campaign = self::get_mautic_campaign_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Comunicaciones Mautic', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Mautic administra los contactos, campañas y comunicaciones. WordPress sólo guarda la consulta y sincroniza el contacto.', 'flacso-uruguay'); ?></p>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                <h2><?php esc_html_e('Conexión Mautic', 'flacso-uruguay'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr><th>Estado</th><td><input type="hidden" name="<?php echo esc_attr(self::OPTION_MAUTIC_ENABLED); ?>" value="0"><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_MAUTIC_ENABLED); ?>" value="1" <?php checked($mautic['enabled'] ?? false); ?>> <?php esc_html_e('Habilitar sincronización', 'flacso-uruguay'); ?></label></td></tr>
                    <tr><th><label for="<?php echo esc_attr(self::OPTION_MAUTIC_BASE_URL); ?>">URL</label></th><td><input class="regular-text" id="<?php echo esc_attr(self::OPTION_MAUTIC_BASE_URL); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_BASE_URL); ?>" value="<?php echo esc_attr($mautic['base_url'] ?? ''); ?>"></td></tr>
                    <tr><th><label for="<?php echo esc_attr(self::OPTION_MAUTIC_AUTH_TYPE); ?>">Autenticación</label></th><td><select id="<?php echo esc_attr(self::OPTION_MAUTIC_AUTH_TYPE); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_AUTH_TYPE); ?>"><option value="basic" <?php selected($mautic['auth_type'] ?? '', 'basic'); ?>>Basic</option><option value="bearer" <?php selected($mautic['auth_type'] ?? '', 'bearer'); ?>>Bearer</option></select></td></tr>
                    <tr><th>Credenciales</th><td><input class="regular-text" name="<?php echo esc_attr(self::OPTION_MAUTIC_USERNAME); ?>" placeholder="Usuario" value="<?php echo esc_attr($mautic['username'] ?? ''); ?>"><br><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION_MAUTIC_PASSWORD); ?>" placeholder="Contraseña"><br><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION_MAUTIC_TOKEN); ?>" placeholder="Token Bearer"></td></tr>
                </table>
                <h2><?php esc_html_e('Campaña de Consultas', 'flacso-uruguay'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr><th>Incorporación</th><td><input type="hidden" name="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_ENABLED); ?>" value="0"><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_ENABLED); ?>" value="1" <?php checked($campaign['enabled']); ?>> <?php esc_html_e('Incorporar contactos automáticamente', 'flacso-uruguay'); ?></label></td></tr>
                    <tr><th><label for="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID); ?>">ID de campaña</label></th><td><input class="small-text" type="number" min="0" id="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID); ?>" name="<?php echo esc_attr(self::OPTION_MAUTIC_CAMPAIGN_CONSULTAS_ID); ?>" value="<?php echo esc_attr((string) $campaign['consultas_id']); ?>"></td></tr>
                </table>
                <?php submit_button(__('Guardar configuración Mautic', 'flacso-uruguay')); ?>
            </form>
        </div>
        <?php
    }
}
