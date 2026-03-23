<?php

if (!defined('ABSPATH')) {
    exit;
}

const FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK = 'flacso_charlas_abiertas_webhook_url';
const FLACSO_CHARLAS_ABIERTAS_WEBHOOK_HARDCODED = 'https://script.google.com/macros/s/AKfycbw3pBICUwynHaNGR-GSBxhNBqy1ikzGqZhGY0HJEeci9aAwZXtSkHX5hmwWBK-9S4Yc/exec';

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
    return flacso_charlas_abiertas_extract_google_webapp_id($value);
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

    add_settings_section(
        'flacso_charlas_abiertas_webhook_section',
        'Webhook',
        '__return_false',
        'flacso-charlas-abiertas-settings'
    );

    add_settings_field(
        'flacso_charlas_abiertas_webhook_url_field',
        'Google Apps Script Deployment ID',
        'flacso_charlas_abiertas_render_webhook_field',
        'flacso-charlas-abiertas-settings',
        'flacso_charlas_abiertas_webhook_section'
    );
}

function flacso_charlas_abiertas_render_webhook_field() {
    $deployment_id = get_option(FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK, '');
    $resolved_url = flacso_charlas_abiertas_build_google_webhook_url_from_id($deployment_id);
    ?>
    <input
        type="text"
        name="<?php echo esc_attr(FLACSO_CHARLAS_ABIERTAS_OPTION_WEBHOOK); ?>"
        value="<?php echo esc_attr($deployment_id); ?>"
        class="regular-text"
        placeholder="AKfycbz..."
        spellcheck="false"
        autocapitalize="off"
        autocorrect="off"
    />
    <p class="description">Ingresa solo el <strong>Deployment ID</strong> de Google Apps Script. También puedes pegar la URL completa: se extraerá el ID automáticamente.</p>
    <?php if (!empty($resolved_url)) : ?>
        <p class="description">Webhook resuelto: <code><?php echo esc_html($resolved_url); ?></code></p>
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
        <p>Configura el endpoint de Google Apps Script para procesar inscripciones.</p>
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
    return esc_url_raw(FLACSO_CHARLAS_ABIERTAS_WEBHOOK_HARDCODED);
}
