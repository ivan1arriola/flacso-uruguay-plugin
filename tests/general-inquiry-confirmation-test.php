<?php
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('FC_TESTING', true);
$GLOBALS['transients'] = [];
function check($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
function add_action(...$args) {}
function get_query_var($name) { return 1; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim($value); }
function sanitize_email($value) { return trim($value); }
function absint($value) { return abs((int) $value); }
function wp_parse_url(...$args) { return parse_url(...$args); }
function status_header(...$args) {}
function nocache_headers() {}
function get_bloginfo($key) { return 'FLACSO'; }
function add_filter(...$args) {}
function get_header() {}
function get_footer() {}
function home_url() { return 'https://flacso.test/'; }
function __($text, $domain = null) { return $text; }
function esc_html($value) { return htmlspecialchars($value); }
function esc_url($value) { return $value; }
function esc_html_e($text, $domain = null) { echo esc_html($text); }
function wp_json_encode($value) { return json_encode($value); }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function fc_confirmation_key(string $reference): string { return 'fc_conf_' . md5($reference); }
require dirname(__DIR__) . '/modules/formularios/includes/confirmacion-consulta.php';
function render($reference) {
    $_GET = ['fc_confirmacion_consulta' => 1, 'fc_token' => $reference];
    ob_start(); fc_maybe_render_gracias_page(); return ob_get_clean();
}
set_transient(fc_confirmation_key('REF1'), ['nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'ana@example.org', 'asunto' => 'Horarios', 'ok' => true], 1800);
$first = render('REF1');
check(strpos($first, "flacsoMetaTrack('Lead'") !== false && strpos($first, 'consulta-general-REF1') !== false, 'First confirmed view tracks Lead with a stable eventID');
check(strpos(render('REF1'), "flacsoMetaTrack('Lead'") === false, 'Reload does not track Lead again');
$unknown = render('NOPE');
check(strpos($unknown, "flacsoMetaTrack('Lead'") === false && strpos($unknown, 'Tu consulta') !== false, 'Unknown reference shows generic text without Lead');
set_transient(fc_confirmation_key('REF2'), ['nombre' => 'Ana', 'email' => 'ana@example.org', 'ok' => false], 1800);
check(strpos(render('REF2'), "flacsoMetaTrack('Lead'") === false, 'Failed submission never tracks Lead');
$_GET = ['fc_confirmacion_consulta' => 1, 'fc_reference' => 'REF1'];
ob_start(); fc_maybe_render_gracias_page(); $ref_only = ob_get_clean();
check(strpos($ref_only, "flacsoMetaTrack('Lead'") === false && strpos($ref_only, 'ana@example.org') === false, 'Reference-only URL renders no Lead and no personal data');
check(strpos($first, 'Ana') !== false, 'Confirmed view greets the visitor');
echo "OK general inquiry confirmation tracks Lead once\n";
