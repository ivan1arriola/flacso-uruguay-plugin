<?php
// fc_render_form: 'mostrar_encabezado' => false omite el h2 y la introduccion, pero conserva "Campos obligatorios".
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
function enc_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function add_action(...$a) {}
function add_filter(...$a) {}
function fc_enqueue_assets() {}
function fc_confirmation_key($t) { return $t; }
function get_transient($k) { return false; }
function shortcode_atts($defaults, $atts) { return array_merge($defaults, array_intersect_key((array) $atts, $defaults)); }
function wp_validate_boolean($v) { return is_string($v) && 'false' === strtolower($v) ? false : (bool) $v; }
function wp_unslash($v) { return $v; }
function sanitize_key($v) { return (string) $v; }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_email($v) { return (string) $v; }
function esc_html($v) { return htmlspecialchars((string) $v); }
function esc_attr($v) { return htmlspecialchars((string) $v); }
function esc_url($v) { return (string) $v; }
function esc_html__($v, $d = null) { return $v; }
function __($v, $d = null) { return $v; }
function esc_html_e($v, $d = null) { echo $v; }
function admin_url($p = '') { return '/wp-admin/' . $p; }
function wp_nonce_field(...$a) {}
function wp_get_referer() { return ''; }

require dirname(__DIR__) . '/modules/formularios/includes/form-render.php';

$con = fc_render_form([]);
enc_assert(strpos($con, '<h2') !== false, 'por defecto se imprime el h2');
enc_assert(strpos($con, 'Completá los datos') !== false, 'por defecto se imprime la introduccion');

foreach ([false, 'false', '0', 0] as $valor) {
    $sin = fc_render_form(['mostrar_encabezado' => $valor]);
    enc_assert(strpos($sin, '<h2') === false, 'sin encabezado no hay h2 (' . var_export($valor, true) . ')');
    enc_assert(strpos($sin, 'Completá los datos') === false, 'sin encabezado no hay introduccion');
    enc_assert(strpos($sin, 'Campos obligatorios') !== false, 'se conserva la indicacion de campos obligatorios');
    enc_assert(strpos($sin, 'id="fc-form"') !== false, 'el formulario sigue presente');
}
echo "OK formularios-render-encabezado\n";
