<?php
// L16 (H-A01 y H-A02): errores visibles y accesibles en el formulario general y estados de carga/éxito/error en mailing.

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

function l16_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('add_shortcode')) { function add_shortcode(...$a) {} }
if (!function_exists('fc_enqueue_assets')) { function fc_enqueue_assets() {} }
if (!function_exists('fc_confirmation_key')) { function fc_confirmation_key($t) { return (string) $t; } }
if (!function_exists('get_transient')) { function get_transient($k) { return false; } }
if (!function_exists('shortcode_atts')) {
    function shortcode_atts($defaults, $atts, $shortcode = '') {
        return array_merge($defaults, array_intersect_key((array) $atts, $defaults));
    }
}
if (!function_exists('wp_validate_boolean')) {
    function wp_validate_boolean($v) { return is_string($v) && 'false' === strtolower($v) ? false : (bool) $v; }
}
if (!function_exists('wp_unslash')) { function wp_unslash($v) { return $v; } }
if (!function_exists('sanitize_key')) {
    function sanitize_key($v) { return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $v)); }
}
if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class($c, $fallback = '') {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c);
        return $sanitized !== '' ? $sanitized : (string) $fallback;
    }
}
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v) { return trim(strip_tags((string) $v)); } }
if (!function_exists('sanitize_email')) { function sanitize_email($v) { return trim((string) $v); } }
if (!function_exists('esc_html')) { function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_attr')) { function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url($v) { return (string) $v; } }
if (!function_exists('esc_html__')) { function esc_html__($v, $d = null) { return (string) $v; } }
if (!function_exists('esc_attr__')) { function esc_attr__($v, $d = null) { return (string) $v; } }
if (!function_exists('__')) { function __($v, $d = null) { return (string) $v; } }
if (!function_exists('esc_html_e')) { function esc_html_e($v, $d = null) { echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_attr_e')) { function esc_attr_e($v, $d = null) { echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return '/wp-admin/' . ltrim((string) $p, '/'); } }
if (!function_exists('wp_nonce_field')) { function wp_nonce_field(...$a) {} }
if (!function_exists('wp_get_referer')) { function wp_get_referer() { return ''; } }
if (!function_exists('wp_generate_uuid4')) { function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000001'; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($data, $flags = 0, $depth = 512) { return json_encode($data, $flags, $depth); } }

$root = dirname(__DIR__);
require $root . '/modules/formularios/includes/form-render.php';
require $root . '/modules/mailing/includes/class-flacso-mailing-subscription.php';

// 1. H-A01: formulario general sin display:none inline, con aria-describedby y mensajes por campo.
$_GET = [];
$form_html = fc_render_form([]);
l16_assert(
    preg_match('/class="[^"]*invalid-feedback[^"]*"[^>]*style="[^"]*display\s*:\s*none/i', $form_html) !== 1
        && preg_match('/style="[^"]*display\s*:\s*none[^"]*"[^>]*class="[^"]*invalid-feedback/i', $form_html) !== 1,
    'Ningún .invalid-feedback del formulario general oculta el error con style inline display:none'
);

foreach (['fc_nombre', 'fc_apellido', 'fc_email', 'fc_asunto', 'fc_mensaje'] as $field) {
    $error_id = $field . '_error';
    l16_assert(
        strpos($form_html, 'id="' . $error_id . '"') !== false && strpos($form_html, 'role="alert"') !== false,
        "El campo {$field} tiene contenedor de error #{$error_id} con role=\"alert\""
    );
    l16_assert(
        preg_match('/id="' . preg_quote($field, '/') . '"[^>]*aria-describedby="' . preg_quote($error_id, '/') . '"/', $form_html) === 1,
        "El campo {$field} referencia #{$error_id} vía aria-describedby"
    );
}
l16_assert(
    strpos($form_html, 'id="fc_telefono_hint"') !== false && strpos($form_html, 'id="fc_telefono_error"') !== false,
    'fc_telefono define hint y contenedor de error propios'
);
l16_assert(
    preg_match('/id="fc_telefono"[^>]*aria-describedby="fc_telefono_hint fc_telefono_error"/', $form_html) === 1,
    'fc_telefono combina ayuda y error en aria-describedby="fc_telefono_hint fc_telefono_error"'
);

$css = str_replace("\r\n", "\n", (string) file_get_contents($root . '/modules/formularios/assets/css/formulario-consultas.css'));
l16_assert(
    preg_match('/\.flacso-formulario-consultas\s+\.fc-form-wrapper\s+\.invalid-feedback\s*\{[^}]*display:\s*none;/s', $css) === 1,
    'formulario-consultas.css oculta .invalid-feedback por defecto fuera de style inline'
);
foreach (['.was-validated .form-control:invalid', '.form-control.is-invalid', '.form-control[aria-invalid="true"]'] as $selector) {
    l16_assert(
        strpos($css, $selector) !== false,
        "formulario-consultas.css contempla {$selector} para mostrar el error"
    );
}

$js = (string) file_get_contents($root . '/modules/formularios/assets/js/formulario-consultas.js');
l16_assert(
    strpos($js, 'aria-invalid') !== false && strpos($js, 'is-invalid') !== false,
    'formulario-consultas.js sincroniza aria-invalid e is-invalid por campo'
);

// 2. H-A02: formulario de mailing con <label>, aria-describedby, estado de carga y lectura de flacso_mailing_status.
$_GET = [];
$mailing_default = Flacso_Mailing_Subscription::render_form([
    'form_id' => 'home',
    'button_label' => '',
    'consent_text' => '',
]);
l16_assert(
    preg_match('/<label\s+for="[^"]+"[^>]*>.*?(?:Correo|Email)/is', $mailing_default) === 1
        && preg_match('/<label\s+for="[^"]+"[^>]*>.*?Nombre/is', $mailing_default) === 1,
    'mailing render_form incluye <label for="..."> visibles para email y first_name'
);
l16_assert(
    preg_match('/name="email"[^>]*aria-describedby="([^"]+)"/', $mailing_default, $m_email) === 1
        && strpos($mailing_default, 'id="' . $m_email[1] . '"') !== false,
    'mailing email vincula aria-describedby con su mensaje de error'
);
l16_assert(
    preg_match('/name="first_name"[^>]*aria-describedby="([^"]+)"/', $mailing_default, $m_first) === 1
        && strpos($mailing_default, 'id="' . $m_first[1] . '"') !== false,
    'mailing first_name vincula aria-describedby con su mensaje de error'
);
l16_assert(
    strpos($mailing_default, 'data-loading-text=') !== false
        && strpos($mailing_default, 'aria-busy=') !== false
        && strpos($mailing_default, 'data-flacso-mailing-feedback') !== false,
    'mailing incluye estado de carga (data-loading-text, aria-busy) y región de feedback'
);
l16_assert(
    strpos($mailing_default, 'Suscribirme') !== false
        && strpos($mailing_default, 'Acepto recibir novedades y comunicaciones institucionales.') !== false,
    'mailing usa los textos por defecto cuando button_label y consent_text llegan vacíos'
);

$_GET = ['flacso_mailing_status' => 'success'];
$mailing_ok = Flacso_Mailing_Subscription::render_form(['form_id' => 'ok']);
l16_assert(
    preg_match('/class="[^"]*flacso-mailing-notice[^"]*is-success[^"]*"[^>]*role="status"/', $mailing_ok) === 1,
    'mailing muestra aviso visible is-success con role="status" tras éxito'
);

foreach (['invalid', 'rate_limited', 'error'] as $err_status) {
    $_GET = ['flacso_mailing_status' => $err_status];
    $mailing_err = Flacso_Mailing_Subscription::render_form(['form_id' => 'err_' . $err_status]);
    l16_assert(
        preg_match('/class="[^"]*flacso-mailing-notice[^"]*is-error[^"]*"[^>]*role="alert"/', $mailing_err) === 1,
        "mailing muestra aviso visible is-error con role=\"alert\" para {$err_status}"
    );
}
$_GET = [];

echo "OK formularios-field-errors-and-mailing\n";
