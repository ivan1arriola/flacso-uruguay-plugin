<?php
// Comportamiento (no texto) del menú reducido y de la guarda de pantallas de la Asistente Académica.

define('ABSPATH', __DIR__ . '/');
define('FLACSO_URUGUAY_PATH', dirname(__DIR__) . '/');
define('FLACSO_URUGUAY_URL', 'http://example.test/');
define('FLACSO_URUGUAY_VERSION', 'test');

final class WP_User { public $roles; public function __construct(array $roles) { $this->roles = $roles; } }
final class WP_Screen { public $base = 'toplevel_page_x'; public $post_type = ''; public $action = ''; }
final class FLACSO_Admin_Panel { public const PAGE_SLUG = 'flacso-panel'; }
final class WP_Die extends Exception {}

$GLOBALS['test_user'] = new WP_User(['gestion_web']);
$GLOBALS['test_caps'] = [];
$GLOBALS['removed'] = [];

function add_action() {}
function add_filter() {}
function is_admin() { return true; }
function wp_get_current_user() { return $GLOBALS['test_user']; }
function wp_doing_ajax() { return false; }
function __($text) { return $text; }
function apply_filters($tag, $value) { return $value; }
function sanitize_key($value) { return strtolower((string) $value); }
function wp_unslash($value) { return $value; }
function esc_html__($text) { return $text; }
function current_user_can($cap) { return !empty($GLOBALS['test_caps'][$cap]); }
function remove_menu_page($slug) { $GLOBALS['removed'][] = $slug; }
function wp_die($message) { throw new WP_Die((string) $message); }

function check($condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "Fallo: {$message}\n"); exit(1); }
}

require dirname(__DIR__) . '/includes/core/class-flacso-academic-assistant.php';

// --- simplify_menu ---
$menu = [['x', 'read', 'flacso-panel'], ['x', 'read', 'edit.php?post_type=oferta-academica'], ['x', 'read', 'tools.php']];
$submenu = ['flacso-panel' => [['viejo', 'read', 'viejo']]];
FLACSO_Academic_Assistant::simplify_menu();

check($GLOBALS['removed'] === ['edit.php?post_type=oferta-academica', 'tools.php'], 'quita todas las raíces menos el panel');
$slugs = array_column($submenu['flacso-panel'], 2);
foreach (['flacso-panel', 'edit.php?post_type=oferta-academica', 'edit.php?post_type=seminario', 'edit.php?post_type=docente', 'admin.php?page=flacso-consultas'] as $expected) {
    check(in_array($expected, $slugs, true), "la Asistente conserva {$expected}");
}
check(!in_array('viejo', $slugs, true), 'descarta entradas ajenas del submenú del panel');

// --- guard_screens ---
function guard(string $page, array $caps): bool {
    $GLOBALS['test_caps'] = $caps;
    $_GET['page'] = $page;
    try { FLACSO_Academic_Assistant::guard_screens(new WP_Screen()); return true; }
    catch (WP_Die $e) { return false; }
}
check(guard('flacso-panel', []), 'el panel está permitido');
check(guard('flacso-consultas', ['flacso_view_inquiries' => true]), 'con permiso de lectura, Consultas está permitida');
check(guard('flacso-consultas-campanas', ['flacso_view_inquiries' => true]), 'con permiso de lectura, sus pestañas también');
check(!guard('flacso-consultas', []), 'sin permiso de lectura, Consultas sigue bloqueada');
check(!guard('flacso-sistema', ['flacso_view_inquiries' => true]), 'otras herramientas siguen reservadas a administración');

echo "OK academic assistant menu behavior\n";
