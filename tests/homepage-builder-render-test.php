<?php
/** El builder de portada renderiza con las secciones propias y las agregadas por filtro. */
define('ABSPATH', __DIR__ . '/');
function builder_assert($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
function add_shortcode($tag, $callback) {}
function add_filter($hook, $callback) { $GLOBALS['filters'][$hook][] = $callback; }
function apply_filters($hook, $value) {
    foreach ($GLOBALS['filters'][$hook] ?? [] as $callback) { $value = $callback($value); }
    return $value;
}
function sanitize_key($key) { return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $key)); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function is_admin() { return false; }
function do_action($hook, ...$args) { $GLOBALS['actions'][] = $hook . ':' . ($args[0] ?? ''); }
class Flacso_Main_Page_Settings {
    public static function is_section_visible($key) { return $key !== 'congreso'; }
    public static function get_section_label($key) { return strtoupper($key); }
    public static function get_homepage_section_order() { return ['eventos', 'hero', 'contacto']; }
}
foreach (['hero', 'novedades', 'quienes', 'contacto', 'congreso', 'eventos'] as $name) {
    $fn = $name === 'quienes' ? 'flacso_section_quienes_somos_render' : "flacso_section_{$name}_render";
    eval("function {$fn}() { return '<p>{$name}</p>'; }");
}
// Instagram sin definir a propósito: debe omitirse y avisar, no romper la portada.

// Un dominio agrega su sección como lo hace modules/eventos/includes/homepage.php.
add_filter('flacso_homepage_sections', static function (array $sections): array {
    $sections['eventos'] = ['function' => 'flacso_section_eventos_render', 'owner' => 'eventos'];
    return $sections;
});

require dirname(__DIR__) . '/modules/main-page/sections/landing-page.php';

$sections = flacso_homepage_builder_get_sections();
foreach (['hero', 'novedades', 'quienes', 'instagram', 'congreso', 'contacto', 'eventos'] as $key) {
    builder_assert(isset($sections[$key]), "la sección {$key} está disponible");
}
builder_assert($sections['eventos']['owner'] === 'eventos', 'la sección del dominio conserva su propietario');

$html = flacso_homepage_builder_render();
builder_assert($html !== '', 'la portada no queda en blanco');
builder_assert(strpos($html, 'data-section-key="hero"') !== false, 'incluye el hero');
builder_assert(strpos($html, 'congreso') === false, 'respeta la visibilidad configurada');
builder_assert(in_array('flacso_homepage_renderer_missing:instagram', $GLOBALS['actions'] ?? [], true), 'avisa del renderer ausente sin romper');
$pos = array_map(static fn($k) => strpos($html, 'data-section-key="' . $k . '"'), ['eventos', 'hero', 'contacto', 'novedades']);
builder_assert($pos === array_values(array_filter($pos, static fn($p) => $p !== false)), 'todas las secciones visibles se renderizan');
builder_assert($pos[0] < $pos[1] && $pos[1] < $pos[2] && $pos[2] < $pos[3], 'respeta el orden configurado y deja el resto al final');
echo "OK homepage builder render\n";
