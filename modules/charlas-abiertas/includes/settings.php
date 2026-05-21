<?php

if (!defined('ABSPATH')) {
    exit;
}

const FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK = 'flacso_charlas_abiertas_webhook_url';
const FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK_TOKEN = 'flacso_charlas_abiertas_webhook_token';

function flacso_charlas_abiertas_extract_google_webapp_id($value) {
    $raw = trim((string) $value);
    if ('' === $raw) {
        return '';
    }

    $from_url = [];
    if (preg_match('#script\.google\.com/macros/s/([A-Za-z0-9_-]+)/(?:exec|dev)?#i', $raw, $from_url)) {
        return $from_url[1];
    }

    if (preg_match('/^[A-Za-z0-9_-]+$/', $raw)) {
        return $raw;
    }

    return '';
}

function flacso_charlas_abiertas_build_google_webhook_url_from_id($id) {
    $clean_id = flacso_charlas_abiertas_extract_google_webapp_id($id);
    if ('' === $clean_id) {
        return '';
    }
    return 'https://script.google.com/macros/s/' . rawurlencode($clean_id) . '/exec';
}

function flacso_charlas_abiertas_sanitize_webhook_url($value) {
    $raw = trim((string) $value);
    if ('' === $raw) {
        return '';
    }

    $google_id = flacso_charlas_abiertas_extract_google_webapp_id($raw);
    if ('' !== $google_id) {
        return flacso_charlas_abiertas_build_google_webhook_url_from_id($google_id);
    }

    return esc_url_raw($raw);
}

function flacso_charlas_abiertas_sanitize_webhook_token($value) {
    return trim((string) $value);
}

add_action('admin_init', 'flacso_charlas_abiertas_register_settings');
function flacso_charlas_abiertas_register_settings() {
    register_setting(
        'flacso_charlas_abiertas_settings_group',
        FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK,
        [
            'type' => 'string',
            'sanitize_callback' => 'flacso_charlas_abiertas_sanitize_webhook_url',
            'default' => '',
        ]
    );

    register_setting(
        'flacso_charlas_abiertas_settings_group',
        FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK_TOKEN,
        [
            'type' => 'string',
            'sanitize_callback' => 'flacso_charlas_abiertas_sanitize_webhook_token',
            'default' => '',
        ]
    );

    add_settings_section(
        'flacso_charlas_abiertas_webhook_section',
        'Webhook',
        '__return_false',
        'flacso-charlas-abiertas-settings'
    );

    add_settings_field(
        'flacso_charlas_abiertas_webhook_url_field',
        'Webhook URL',
        'flacso_charlas_abiertas_render_webhook_field',
        'flacso-charlas-abiertas-settings',
        'flacso_charlas_abiertas_webhook_section'
    );

    add_settings_field(
        'flacso_charlas_abiertas_webhook_token_field',
        'Webhook Token',
        'flacso_charlas_abiertas_render_webhook_token_field',
        'flacso-charlas-abiertas-settings',
        'flacso_charlas_abiertas_webhook_section'
    );
}

function flacso_charlas_abiertas_render_webhook_field() {
    $webhook_url = get_option(FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK, '');
    $google_id = flacso_charlas_abiertas_extract_google_webapp_id($webhook_url);
    ?>
    <input
        type="text"
        name="<?php echo esc_attr(FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK); ?>"
        value="<?php echo esc_attr($webhook_url); ?>"
        class="regular-text"
        placeholder="https://tu-dominio.com/api/charlas/inscripciones"
        spellcheck="false"
        autocapitalize="off"
        autocorrect="off"
    />
    <p class="description">
        Ingresa la <strong>URL completa</strong> del webhook de <code>flacso-editor</code>.
        La ruta sugerida es <code>/api/charlas/inscripciones</code>.
        Por compatibilidad, también puedes pegar la URL o el Deployment ID de Google Apps Script.
    </p>
    <?php if (!empty($webhook_url)) : ?>
        <p class="description">Webhook configurado: <code><?php echo esc_html($webhook_url); ?></code></p>
    <?php endif; ?>
    <?php if (!empty($google_id)) : ?>
        <p class="description">Compatibilidad Google detectada: <code><?php echo esc_html($google_id); ?></code></p>
    <?php endif; ?>
    <?php
}

function flacso_charlas_abiertas_render_webhook_token_field() {
    $webhook_token = get_option(FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK_TOKEN, '');
    ?>
    <input
        type="password"
        name="<?php echo esc_attr(FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK_TOKEN); ?>"
        value="<?php echo esc_attr($webhook_token); ?>"
        class="regular-text"
        placeholder="Pega aquí el mismo token configurado en Vercel"
        spellcheck="false"
        autocomplete="new-password"
        autocapitalize="off"
        autocorrect="off"
    />
    <p class="description">
        Opcional pero recomendado: si en <code>flacso-editor</code> configuras la variable
        <code>FLACSO_CHARLAS_WEBHOOK_TOKEN</code>, pega aquí exactamente el mismo valor.
        El plugin lo enviará en el header <code>Authorization: Bearer ...</code>.
    </p>
    <?php if (!empty($webhook_token)) : ?>
        <p class="description">Token configurado: <code>********</code></p>
    <?php endif; ?>
    <?php
}

add_action('admin_menu', 'flacso_charlas_abiertas_add_settings_page');
function flacso_charlas_abiertas_add_settings_page() {
    add_submenu_page(
        'edit.php?post_type=charla_abierta',
        'Webhook de Charlas Abiertas',
        'Webhook',
        'manage_options',
        'flacso-charlas-abiertas-settings',
        'flacso_charlas_abiertas_render_settings_page',
        99
    );
}

function flacso_charlas_abiertas_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Webhook de Charlas Abiertas</h1>
        <p>Configura el endpoint que recibirá las inscripciones de charlas abiertas.</p>
        <form method="post" action="options.php">
            <?php
            settings_fields('flacso_charlas_abiertas_settings_group');
            do_settings_sections('flacso-charlas-abiertas-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function flacso_charlas_abiertas_get_webhook_url() {
    $url = get_option(FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK, '');
    return is_string($url) ? esc_url_raw($url) : '';
}

function flacso_charlas_abiertas_get_webhook_token() {
    $token = get_option(FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK_TOKEN, '');
    return is_string($token) ? trim($token) : '';
}
