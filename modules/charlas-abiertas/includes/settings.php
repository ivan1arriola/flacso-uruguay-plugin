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

// Decommissioned in favor of unified Integraciones FLACSO page
// add_action('admin_menu', 'flacso_charlas_abiertas_add_settings_page');
function flacso_charlas_abiertas_add_settings_page() {
    // Left as legacy function placeholder
}

function flacso_charlas_abiertas_get_settings_page_url(array $args = []) {
    return add_query_arg(
        array_merge(
            [
                'page' => 'flacso-integraciones',
            ],
            $args
        ),
        admin_url('options-general.php')
    );
}

function flacso_charlas_abiertas_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    wp_safe_redirect(admin_url('options-general.php?page=flacso-integraciones'));
    exit;
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

        <hr>
        <h2>Prueba del webhook</h2>
        <p>Envía una solicitud de prueba al endpoint configurado. Si el destino es FLACSO Editor, esta verificación no crea inscripciones ni envía correos.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('flacso_charlas_abiertas_test_webhook', 'flacso_charlas_abiertas_test_webhook_nonce'); ?>
            <input type="hidden" name="action" value="flacso_charlas_abiertas_test_webhook" />
            <?php submit_button('Probar conexión con el editor', 'secondary', 'submit', false); ?>
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

function flacso_charlas_abiertas_send_webhook_test() {
    $webhook_url = flacso_charlas_abiertas_get_webhook_url();
    if ('' === $webhook_url) {
        return [
            'ok' => false,
            'code' => 0,
            'body' => '',
            'error' => 'No hay endpoint configurado para charlas abiertas.',
            'message' => '',
        ];
    }

    if (!function_exists('flacso_charlas_abiertas_post_webhook')) {
        return [
            'ok' => false,
            'code' => 0,
            'body' => '',
            'error' => 'La utilidad de prueba del webhook no está disponible.',
            'message' => '',
        ];
    }

    $payload = wp_json_encode([
        'test' => true,
        'source' => 'wordpress_admin',
        'requested_at' => current_time('c'),
    ]);
    $post_result = flacso_charlas_abiertas_post_webhook(
        $webhook_url,
        $payload,
        ['X-FLACSO-Webhook-Test' => '1']
    );
    $response = isset($post_result['response']) ? $post_result['response'] : null;

    if (is_wp_error($response)) {
        return [
            'ok' => false,
            'code' => 0,
            'body' => '',
            'error' => $response->get_error_message(),
            'message' => '',
        ];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    $message = '';
    $ok = $code >= 200 && $code < 300;

    if (function_exists('flacso_charlas_abiertas_decode_json_loose')) {
        $decoded = flacso_charlas_abiertas_decode_json_loose($body);
        if (is_array($decoded) && isset($decoded['data']['message'])) {
            $message = sanitize_text_field((string) $decoded['data']['message']);
        } elseif (is_array($decoded) && isset($decoded['error']['message'])) {
            $message = sanitize_text_field((string) $decoded['error']['message']);
        }

        if (is_array($decoded) && isset($decoded['ok']) && false === $decoded['ok']) {
            $ok = false;
        }
    }

    return [
        'ok' => $ok,
        'code' => $code,
        'body' => $body,
        'error' => $ok ? '' : 'HTTP ' . $code,
        'message' => $message,
    ];
}

function flacso_charlas_abiertas_handle_test_webhook() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes.');
    }
    if (
        !isset($_POST['flacso_charlas_abiertas_test_webhook_nonce']) ||
        !wp_verify_nonce(
            wp_unslash($_POST['flacso_charlas_abiertas_test_webhook_nonce']),
            'flacso_charlas_abiertas_test_webhook'
        )
    ) {
        wp_die('Solicitud no válida.');
    }

    $result = flacso_charlas_abiertas_send_webhook_test();
    $args = [
        'flacso_charlas_webhook_test' => $result['ok'] ? 'success' : 'fail',
    ];

    if (!empty($result['code'])) {
        $args['flacso_charlas_webhook_code'] = (int) $result['code'];
    }

    $message = '';
    if (!empty($result['message'])) {
        $message = sanitize_text_field((string) $result['message']);
    } elseif (!empty($result['error'])) {
        $message = sanitize_text_field((string) $result['error']);
    }

    if ('' !== $message) {
        $args['flacso_charlas_webhook_message'] = $message;
    }

    $redirect_url = class_exists('FLACSO_Integrations_Settings')
        ? FLACSO_Integrations_Settings::get_redirect_url_from_request($args, flacso_charlas_abiertas_get_settings_page_url())
        : flacso_charlas_abiertas_get_settings_page_url($args);
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_flacso_charlas_abiertas_test_webhook', 'flacso_charlas_abiertas_handle_test_webhook');

function flacso_charlas_abiertas_admin_notices() {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ('flacso-charlas-abiertas-settings' !== $page || !isset($_GET['flacso_charlas_webhook_test'])) {
        return;
    }

    $status = sanitize_key(wp_unslash($_GET['flacso_charlas_webhook_test']));
    $code = isset($_GET['flacso_charlas_webhook_code']) ? absint(wp_unslash($_GET['flacso_charlas_webhook_code'])) : 0;
    $message = isset($_GET['flacso_charlas_webhook_message'])
        ? sanitize_text_field(wp_unslash($_GET['flacso_charlas_webhook_message']))
        : '';

    if ('success' === $status) {
        echo '<div class="notice notice-success is-dismissible"><p>La app respondió correctamente y aceptó la prueba del webhook.</p></div>';
        return;
    }

    if (401 === $code) {
        $notice = 'La app rechazó el token del webhook. Revisá que coincida con FLACSO_CHARLAS_WEBHOOK_TOKEN.';
    } elseif (404 === $code) {
        $notice = 'La URL del webhook respondió 404. Revisá que el endpoint exista en la app.';
    } elseif ($code >= 500) {
        $notice = 'La app respondió con un error interno.';
    } elseif ('' !== $message) {
        $notice = $message;
    } else {
        $notice = 'No se pudo validar la conexión con la app. Revisá la URL configurada y el token.';
    }

    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($notice) . '</p></div>';
}
add_action('admin_notices', 'flacso_charlas_abiertas_admin_notices');
