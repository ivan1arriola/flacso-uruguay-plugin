<?php

define('ABSPATH', __DIR__ . '/../');

$GLOBALS['academic_admin_submenus'] = [];

function __($text, $domain = null) { return $text; }
function is_admin() { return true; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_submenu_page($parent, $page_title, $menu_title, $capability, $slug, $callback) {
    $GLOBALS['academic_admin_submenus'][] = $slug;
}

require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-data-admin.php';

Oferta_Data_Admin::add_admin_menu();
if (in_array('flacso-oferta-data', $GLOBALS['academic_admin_submenus'], true)) {
    fwrite(STDERR, "Fallo: la pagina flacso-oferta-data sigue registrada\n");
    exit(1);
}
if (!in_array('flacso-oferta-api-docs', $GLOBALS['academic_admin_submenus'], true)) {
    fwrite(STDERR, "Fallo: se retiro accidentalmente la documentacion administrativa de API\n");
    exit(1);
}

$module_init = file_get_contents(__DIR__ . '/../modules/oferta-academica/init.php');
if (strpos($module_init, 'class-oferta-data-migration.php') !== false || strpos($module_init, 'Oferta_Data_Migration::init') !== false) {
    fwrite(STDERR, "Fallo: la pagina flacso-oferta-migracion sigue cargada\n");
    exit(1);
}

fwrite(STDOUT, "OK obsolete academic admin pages removed\n");
