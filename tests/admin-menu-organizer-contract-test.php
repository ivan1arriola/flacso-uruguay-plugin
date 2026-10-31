<?php
// Contrato del organizador del menú: seis grupos, sin cambiar slug, etiqueta, capacidad ni callback.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['caps'] = ['read' => true, 'edit_posts' => true, 'manage_options' => true, 'switch_themes' => true];
function __($text, $domain = '') { return $text; }
function is_admin() { return false; }
function add_action() {}
function add_filter() {}
function current_user_can($cap) { return !empty($GLOBALS['caps'][$cap]); }
final class FLACSO_Admin_Panel { public const PAGE_SLUG = 'flacso-panel'; }
require dirname(__DIR__) . '/includes/core/class-flacso-admin-menu-organizer.php';

function check($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}

// --- Tarea 1: manifiesto y clasificación -----------------------------------------------------
$groups = FLACSO_Admin_Menu_Organizer::groups();
check(array_keys($groups) === ['inicio', 'formacion', 'contenidos', 'inscripciones', 'diseno', 'sistema'], 'Hay seis grupos aprobados, en orden');
check(array_values($groups) === ['Inicio FLACSO', 'Formación', 'Sitio y contenidos', 'Inscripciones y consultas', 'Diseño del sitio', 'Sistema y herramientas'], 'Las etiquetas son las del diseño aprobado');

$manifest = FLACSO_Admin_Menu_Organizer::group_manifest();
check(FLACSO_Admin_Menu_Organizer::manifest_duplicates() === [], 'Ningún slug está asignado a dos grupos');
check(array_diff(array_unique(array_values($manifest)), array_keys($groups)) === [], 'Todo slug del manifiesto apunta a un grupo permitido');

$expected = [
    'flacso-panel' => 'inicio', 'index.php' => 'inicio', 'update-core.php' => 'inicio',
    'edit.php?post_type=oferta-academica' => 'formacion', 'edit.php?post_type=convenio' => 'formacion', 'edit.php?post_type=docente' => 'formacion',
    'post-new.php?post_type=seminario' => 'formacion',
    'edit.php' => 'contenidos', 'edit.php?post_type=page' => 'contenidos', 'upload.php' => 'contenidos', 'edit.php?post_type=flacso_faq' => 'contenidos',
    'edit-tags.php?taxonomy=category' => 'contenidos',
    'flacso-consultas' => 'inscripciones', 'fc_options_page' => 'inscripciones', 'flacso-correos' => 'inscripciones', 'flacso-consultas-exportar' => 'inscripciones',
    'themes.php' => 'diseno', 'customize.php' => 'diseno', 'kadence' => 'diseno', 'kadence-starter-templates' => 'diseno',
    'flacso-sistema' => 'sistema', 'options-general.php' => 'sistema', 'users.php' => 'sistema', 'tools.php' => 'sistema', 'plugins.php' => 'sistema',
    'googlesitekit-dashboard' => 'sistema', 'rank-math' => 'sistema', 'smush' => 'sistema', 'action-scheduler' => 'sistema',
];
foreach ($expected as $slug => $group) {
    check(FLACSO_Admin_Menu_Organizer::group_for_slug($slug) === $group, "$slug va en $group");
}
check(FLACSO_Admin_Menu_Organizer::group_for_slug('ruta-desconocida-de-un-plugin') === 'sistema', 'Una ruta sin clasificar usa Sistema y herramientas');
check(FLACSO_Admin_Menu_Organizer::group_for_slug('tools.php?page=cualquiera') === 'sistema', 'Una ruta desconocida de herramientas usa Sistema y herramientas');

// --- Tarea 2: reubicación ---------------------------------------------------------------------
function fresh_menu(): void {
    global $menu, $submenu, $admin_page_hooks, $wp_filter, $_registered_pages;
    $menu = [
        2 => ['FLACSO', 'edit_posts', 'flacso-panel', 'FLACSO Uruguay', 'menu-top', 'toplevel_page_flacso-panel', 'dashicons-building'],
        4 => ['', 'read', 'separator1', '', 'wp-menu-separator'],
        5 => ['Entradas', 'edit_posts', 'edit.php', '', 'menu-top'],
        26 => ['Convenios', 'edit_posts', 'edit.php?post_type=convenio', '', 'menu-top'],
        59 => ['', 'read', 'separator2', '', 'wp-menu-separator'],
        61 => ['Apariencia', 'switch_themes', 'themes.php', '', 'menu-top'],
        80 => ['Ajustes', 'manage_options', 'options-general.php', '', 'menu-top'],
        85 => ['Rank Math SEO', 'manage_options', 'rank-math', '', 'menu-top'],
        99 => ['Ayuda', 'read', 'https://ejemplo.org/ayuda', '', 'menu-top'],
    ];
    $submenu = [
        'flacso-panel' => [
            ['Resumen', 'edit_posts', 'flacso-panel', 'Resumen FLACSO'],
            ['Consultas', 'edit_posts', 'flacso-consultas', 'Consultas'],
            ['Sistema', 'manage_options', 'flacso-sistema', 'Sistema'],
        ],
        'edit.php' => [['Todas las entradas', 'edit_posts', 'edit.php'], ['Añadir nueva', 'edit_posts', 'post-new.php']],
        'edit.php?post_type=convenio' => [['Convenios', 'edit_posts', 'edit.php?post_type=convenio'], ['Migración', 'manage_options', 'flacso-convenios-migration', 'Migración de convenios']],
        'themes.php' => [['Temas', 'switch_themes', 'themes.php']],
        'options-general.php' => [['General', 'manage_options', 'options-general.php'], ['Ajustes de ejemplo', 'manage_options', 'flacso-ajustes-ejemplo', 'Ajustes de ejemplo']],
        'rank-math' => [['Panel', 'manage_options', 'rank-math'], ['Herramientas', 'manage_options', 'rank-math-tools']],
    ];
    $admin_page_hooks = ['flacso-panel' => 'flacso', 'options-general.php' => 'settings', 'rank-math' => 'rank-math', 'edit.php?post_type=convenio' => 'convenios'];
    $wp_filter = ['settings_page_flacso-ajustes-ejemplo' => ['callback-original'], 'convenios_page_flacso-convenios-migration' => ['callback-migracion']];
    $_registered_pages = ['settings_page_flacso-ajustes-ejemplo' => true, 'convenios_page_flacso-convenios-migration' => true];
}
function root_entries(): array { global $submenu; return $submenu['flacso-panel']; }
function slugs(array $entries): array { return array_values(array_map(static fn($e) => $e[2], array_filter($entries, static fn($e) => ($e[4] ?? '') !== 'flacso-menu-group'))); }
function find_entry(string $slug): ?array { foreach (root_entries() as $e) { if ($e[2] === $slug) return $e; } return null; }

fresh_menu();
FLACSO_Admin_Menu_Organizer::organize_menu();
global $menu, $submenu, $wp_filter, $_registered_pages;

check(isset($menu[2]) && $menu[2][2] === 'flacso-panel', 'La raíz FLACSO sigue en el menú');
check(!isset($menu[5], $menu[26], $menu[61], $menu[80], $menu[85]), 'Las secciones se mueven bajo FLACSO');
check(isset($menu[4], $menu[59]), 'Los separadores no se tocan');
check(isset($menu[99]) && $menu[99][2] === 'https://ejemplo.org/ayuda', 'Un enlace externo queda fuera de la reagrupación');

$resumen = find_entry('flacso-panel');
check($resumen !== null && $resumen[0] === 'Resumen' && $resumen[1] === 'edit_posts', 'El panel FLACSO conserva su ruta, etiqueta y capacidad');
foreach ([
    'edit.php' => ['Todas las entradas', 'edit_posts'], 'post-new.php' => ['Añadir nueva', 'edit_posts'],
    'edit.php?post_type=convenio' => ['Convenios', 'edit_posts'], 'flacso-convenios-migration' => ['Migración', 'manage_options'],
    'themes.php' => ['Temas', 'switch_themes'], 'options-general.php' => ['General', 'manage_options'],
    'flacso-ajustes-ejemplo' => ['Ajustes de ejemplo', 'manage_options'], 'rank-math-tools' => ['Herramientas', 'manage_options'],
    'flacso-consultas' => ['Consultas', 'edit_posts'], 'flacso-sistema' => ['Sistema', 'manage_options'],
] as $slug => [$label, $cap]) {
    $entry = find_entry($slug);
    check($entry !== null, "$slug sigue disponible");
    check($entry[0] === $label && $entry[1] === $cap, "$slug conserva etiqueta y capacidad");
}

$headings = array_values(array_filter(root_entries(), static fn($e) => ($e[4] ?? '') === 'flacso-menu-group'));
check(array_map(static fn($e) => $e[0], $headings) === ['Inicio FLACSO', 'Formación', 'Sitio y contenidos', 'Inscripciones y consultas', 'Diseño del sitio', 'Sistema y herramientas'], 'Los seis encabezados aparecen en orden');
check(count(array_unique(array_map(static fn($e) => $e[2], $headings))) === 6, 'Cada encabezado tiene identificador propio');
check(array_unique(array_map(static fn($e) => $e[1], $headings)) === ['read'], 'Los encabezados no exigen más que iniciar sesión');

// Orden: cada ruta queda entre el encabezado de su grupo y el siguiente.
$current = '';
$by_group = [];
foreach (root_entries() as $entry) {
    if (($entry[4] ?? '') === 'flacso-menu-group') { $current = $entry[2]; continue; }
    $by_group[$current][] = $entry[2];
}
check(($by_group['flacso-grupo-inicio'] ?? []) === ['flacso-panel'], 'Inicio FLACSO contiene el panel');
check(($by_group['flacso-grupo-formacion'] ?? []) === ['edit.php?post_type=convenio', 'flacso-convenios-migration'], 'Formación contiene Convenios');
check(($by_group['flacso-grupo-contenidos'] ?? []) === ['edit.php', 'post-new.php'], 'Sitio y contenidos contiene Entradas');
check(in_array('flacso-consultas', $by_group['flacso-grupo-inscripciones'] ?? [], true), 'Inscripciones y consultas contiene la bandeja');
check(($by_group['flacso-grupo-diseno'] ?? []) === ['themes.php'], 'Diseño del sitio contiene Apariencia');
check(in_array('rank-math-tools', $by_group['flacso-grupo-sistema'] ?? [], true) && in_array('options-general.php', $by_group['flacso-grupo-sistema'], true), 'Sistema y herramientas recoge Ajustes y plugins');

// Callbacks: la pantalla movida conserva su callback con el hook que tendrá bajo FLACSO.
check($wp_filter['flacso_page_flacso-ajustes-ejemplo'] === ['callback-original'], 'El callback de una pantalla de Ajustes sigue enlazado tras moverla');
check($wp_filter['flacso_page_flacso-convenios-migration'] === ['callback-migracion'], 'El callback de una pantalla de Convenios sigue enlazado tras moverla');
check(!empty($_registered_pages['flacso_page_flacso-ajustes-ejemplo']), 'La pantalla movida queda registrada como página válida');
check($wp_filter['settings_page_flacso-ajustes-ejemplo'] === ['callback-original'], 'El hook original no se elimina');

// Resaltado de la sección actual.
check(FLACSO_Admin_Menu_Organizer::filter_parent_file('edit.php') === 'flacso-panel', 'Una pantalla del núcleo resalta el menú FLACSO');
check(FLACSO_Admin_Menu_Organizer::filter_submenu_file('') === 'edit.php', 'Sin submenú propio se resalta su entrada original');
check(FLACSO_Admin_Menu_Organizer::filter_parent_file('flacso-panel') === 'flacso-panel', 'La raíz no cambia');
check(FLACSO_Admin_Menu_Organizer::filter_submenu_file('post-new.php') === 'post-new.php', 'Un submenú ya definido se respeta');

// Idempotencia y registro tardío.
$before = root_entries();
FLACSO_Admin_Menu_Organizer::organize_menu();
check(root_entries() === $before, 'Una segunda pasada no duplica entradas ni encabezados');

$menu[120] = ['Plugin tardío', 'manage_options', 'plugin-tardio', '', 'menu-top'];
$submenu['plugin-tardio'] = [['Pantalla tardía', 'manage_options', 'plugin-tardio']];
check(isset($menu[120]), 'Antes de reorganizar, lo registrado tarde no se pierde: sigue como sección propia');
FLACSO_Admin_Menu_Organizer::organize_menu();
check(find_entry('plugin-tardio') !== null && !isset($menu[120]), 'Una nueva pasada recoge lo registrado tarde');
check(slugs(root_entries()) === array_values(array_unique(slugs(root_entries()))), 'Sin rutas duplicadas');

// --- Tarea 3: capacidades y asistentes --------------------------------------------------------
$GLOBALS['caps'] = ['read' => true, 'edit_posts' => true];
fresh_menu();
FLACSO_Admin_Menu_Organizer::organize_menu();
$assistant_headings = array_map(static fn($e) => $e[0], array_values(array_filter(root_entries(), static fn($e) => ($e[4] ?? '') === 'flacso-menu-group')));
check(!in_array('Diseño del sitio', $assistant_headings, true) && !in_array('Sistema y herramientas', $assistant_headings, true), 'Sin capacidades no aparecen grupos vacíos');
check(in_array('Formación', $assistant_headings, true) && in_array('Inscripciones y consultas', $assistant_headings, true), 'Los grupos con entradas permitidas siguen visibles');
$sistema = find_entry('flacso-sistema');
check($sistema !== null && $sistema[1] === 'manage_options', 'La capacidad original se conserva; WordPress sigue ocultando la entrada');
check(find_entry('themes.php')[1] === 'switch_themes', 'No se amplía el acceso a Apariencia');
foreach (root_entries() as $entry) {
    if (($entry[4] ?? '') === 'flacso-menu-group') { continue; }
    foreach (['themes.php' => 'switch_themes', 'options-general.php' => 'manage_options', 'flacso-sistema' => 'manage_options'] as $slug => $cap) {
        if ($entry[2] === $slug) { check($entry[1] === $cap, "$slug no cambia de capacidad"); }
    }
}

echo "OK admin menu organizer contract\n";
