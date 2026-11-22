<?php
/**
 * Menú FLACSO unificado: un solo menú de primer nivel con todo lo que introduce el plugin.
 * Apagado por defecto; con la opción activa, los tipos de contenido se anidan bajo `flacso-panel`
 * y las pantallas de ajustes lo usan como padre. WordPress y otros plugins no se tocan.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
$root = dirname(__DIR__);

function menu_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// Stubs mínimos de WordPress.
$GLOBALS['wp_filters'] = array();
$GLOBALS['wp_options'] = array();
$GLOBALS['wp_submenu_added'] = array();
$GLOBALS['wp_screen'] = null;
$submenu = array();
function add_filter($tag, $callback, $priority = 10, $args = 1) { $GLOBALS['wp_filters'][$tag][] = $callback; return true; }
function add_action($tag, $callback, $priority = 10, $args = 1) { return add_filter($tag, $callback, $priority, $args); }
function apply_filters($tag, $value, ...$rest) {
    foreach ($GLOBALS['wp_filters'][$tag] ?? array() as $callback) { $value = call_user_func($callback, $value, ...$rest); }
    return $value;
}
function get_option($key, $default = false) { return $GLOBALS['wp_options'][$key] ?? $default; }
function __($text, $domain = null) { return $text; }
function is_admin() { return true; }
function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $key)); }
function wp_unslash($value) { return $value; }
function get_current_screen() { return $GLOBALS['wp_screen']; }
function add_submenu_page($parent, $title, $menu_title, $cap, $slug) { $GLOBALS['wp_submenu_added'][] = array($parent, $slug); return $slug; }

require_once $root . '/includes/core/class-flacso-admin-menu.php';

// 1. Apagado por defecto: nada cambia.
menu_assert(false === FLACSO_Admin_Menu::enabled(), 'el menú unificado está apagado por defecto');
menu_assert('options-general.php' === FLACSO_Admin_Menu::parent('options-general.php'), 'apagado: cada pantalla conserva su padre nativo');
menu_assert(true === FLACSO_Admin_Menu::nest_post_types(array('show_in_menu' => true), 'convenio')['show_in_menu'], 'apagado: los tipos de contenido conservan su menú');

// 2. Encendido: padre único y tipos de contenido anidados.
$GLOBALS['wp_options'][FLACSO_Admin_Menu::OPTION] = '1';
menu_assert(true === FLACSO_Admin_Menu::enabled(), 'la opción enciende el menú unificado');
foreach (array('options-general.php', 'tools.php', 'edit.php?post_type=page', 'edit.php?post_type=evento', 'flacso-main-page') as $legacy) {
    menu_assert('flacso-panel' === FLACSO_Admin_Menu::parent($legacy), "encendido: {$legacy} pasa a colgar de FLACSO");
}
foreach (array('oferta-academica', 'articulacion-academica', 'seminario', 'docente', 'programa-academico', 'tabla-precio', 'convenio', 'evento', 'pregunta-frecuente') as $type) {
    menu_assert('flacso-panel' === FLACSO_Admin_Menu::nest_post_types(array('show_in_menu' => true), $type)['show_in_menu'], "encendido: {$type} se anida bajo FLACSO");
}
foreach (array('cohorte', 'edicion', 'page', 'post', 'attachment') as $type) {
    menu_assert(false === FLACSO_Admin_Menu::nest_post_types(array('show_in_menu' => false), $type)['show_in_menu'], "encendido: {$type} no se toca");
}

// 3. El filtro tiene la última palabra (interruptor de emergencia sin tocar la base).
add_filter('flacso_unified_admin_menu', '__return_false');
function __return_false() { return false; }
menu_assert(false === FLACSO_Admin_Menu::enabled(), 'el filtro flacso_unified_admin_menu puede apagar el menú');
$GLOBALS['wp_filters']['flacso_unified_admin_menu'] = array();

// 4. Orden del submenú: lista por uso, lo desconocido al final, otros menús intactos.
$submenu['flacso-panel'] = array(
    array('Sistema', 'manage_options', 'flacso-sistema'),
    array('Ajeno', 'read', 'otra-pantalla'),
    array('Eventos', 'edit_posts', 'edit.php?post_type=evento'),
    array('Resumen', 'read', 'flacso-panel'),
    array('Consultas', 'read', 'flacso-consultas'),
    array('Ofertas', 'read', 'edit.php?post_type=oferta-academica'),
);
$submenu['options-general.php'] = array(array('Generales', 'manage_options', 'options-general.php'));
FLACSO_Admin_Menu::sort_submenu();
$order = array_column($submenu['flacso-panel'], 2);
menu_assert(array('flacso-panel', 'edit.php?post_type=oferta-academica', 'edit.php?post_type=evento', 'flacso-consultas', 'flacso-sistema', 'otra-pantalla') === $order, 'el submenú FLACSO queda ordenado por uso: ' . implode(' | ', $order));
menu_assert(1 === count($submenu['options-general.php']), 'los menús nativos no se tocan');

// 5. "Tipos de oferta" se agrega una sola vez.
$submenu['flacso-panel'] = array();
FLACSO_Admin_Menu::add_taxonomy_submenu();
menu_assert(1 === count($GLOBALS['wp_submenu_added']) && 'flacso-panel' === $GLOBALS['wp_submenu_added'][0][0], 'se agrega el submenú de la taxonomía bajo FLACSO');
$submenu['flacso-panel'] = array(array('Tipos de oferta', 'manage_categories', $GLOBALS['wp_submenu_added'][0][1]));
FLACSO_Admin_Menu::add_taxonomy_submenu();
menu_assert(1 === count($GLOBALS['wp_submenu_added']), 'no se duplica el submenú de la taxonomía');

// 6. Resaltado del menú: tipos de contenido anidados y vistas de Consultas.
$GLOBALS['wp_screen'] = (object) array('post_type' => 'convenio');
menu_assert('flacso-panel' === FLACSO_Admin_Menu::highlight_parent('edit.php?post_type=convenio'), 'el listado de un tipo anidado resalta FLACSO');
$GLOBALS['wp_screen'] = (object) array('post_type' => 'page');
menu_assert('edit.php?post_type=page' === FLACSO_Admin_Menu::highlight_parent('edit.php?post_type=page'), 'las páginas nativas no cambian');
$_GET['page'] = 'flacso-consultas-comparacion';
menu_assert('flacso-panel' === FLACSO_Admin_Menu::highlight_parent('admin.php'), 'una vista de Consultas resalta FLACSO');
menu_assert('flacso-consultas' === FLACSO_Admin_Menu::highlight_submenu(null), 'una vista de Consultas resalta la entrada Consultas');
$GLOBALS['wp_options'][FLACSO_Admin_Menu::OPTION] = '0';
menu_assert('admin.php' === FLACSO_Admin_Menu::highlight_parent('admin.php'), 'apagado: no se altera el resaltado');
unset($_GET['page']);

// 7. Contrato de código: ninguna pantalla propia cuelga de un menú nativo; todas usan el padre único.
$panel = (string) file_get_contents($root . '/includes/core/class-flacso-admin-panel.php');
menu_assert(strpos($panel, "PAGE_SLUG = '" . FLACSO_Admin_Menu::PARENT . "'") !== false, 'el padre del menú coincide con el slug del panel');
$offenders = array();
foreach (array('includes', 'modules') as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') { continue; }
        $code = (string) file_get_contents($file->getPathname());
        if (preg_match("/add_submenu_page\(\s*'(options-general\.php|tools\.php|edit\.php\?post_type=[a-z-]+|flacso-main-page)'/", $code)) {
            $offenders[] = basename($file->getPathname());
        }
    }
}
menu_assert(array() === $offenders, 'pantallas propias colgadas de un menú nativo: ' . implode(', ', $offenders));
$must_use_parent = array(
    'includes/core/class-flacso-system-settings.php',
    'includes/core/class-flacso-integrations-settings.php',
    'includes/core/class-flacso-preinscription-links-admin.php',
    'modules/mailing/includes/class-flacso-mail-settings.php',
    'modules/formularios/includes/settings.php',
    'modules/main-page/includes/class-flacso-main-page-admin.php',
    'modules/oferta-academica/includes/class-carta-settings-admin.php',
    'modules/seminarios/includes/class-seminario-settings.php',
    'modules/eventos/includes/class-cpt-eventos-manager.php',
);
foreach ($must_use_parent as $relative) {
    menu_assert(strpos((string) file_get_contents($root . '/' . $relative), 'FLACSO_Admin_Menu::parent(') !== false, "{$relative} debe usar FLACSO_Admin_Menu::parent()");
}
menu_assert(!file_exists($root . '/modules/main-page/includes/class-flacso-main-page-migrations.php'), 'la pantalla Migraciones fue retirada');
menu_assert(strpos((string) file_get_contents($root . '/modules/main-page/init.php'), 'Main_Page_Migrations') === false, 'la portada ya no carga Migraciones');
menu_assert(strpos((string) file_get_contents($root . '/modules/autoridades/includes/admin-page.php'), 'FLACSO_Admin_Menu::enabled()') !== false, 'Autoridades debe respetar el menú unificado');
menu_assert(strpos((string) file_get_contents($root . '/modules/consultas/includes/class-flacso-consultas-admin.php'), 'FLACSO_Admin_Menu::enabled()') !== false, 'Consultas debe respetar el menú unificado');
menu_assert(strpos((string) file_get_contents($root . '/includes/core/class-flacso-meta-tracking.php'), "options-general.php?page=flacso-integracion-meta") === false, 'el aviso de Meta no enlaza por options-general.php');
menu_assert(strpos((string) file_get_contents($root . '/flacso-uruguay.php'), 'class-flacso-admin-menu.php') !== false, 'el plugin carga el menú unificado');
menu_assert(strpos((string) file_get_contents($root . '/includes/core/class-flacso-system-settings.php'), 'FLACSO_Admin_Menu::OPTION') !== false, 'Sistema expone el interruptor del menú unificado');

echo "OK menú FLACSO unificado\n";
