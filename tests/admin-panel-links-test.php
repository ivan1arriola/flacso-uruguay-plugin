<?php
/**
 * Los accesos del Resumen FLACSO solo pueden apuntar a pantallas de admin que existen.
 * "docentes_panel" (menú Docentes propio) se retiró: las personas se administran desde el
 * menú nativo "Personas / Equipo" (edit.php?post_type=docente).
 */

$root   = dirname(__DIR__);
$source = (string) file_get_contents($root . '/includes/core/class-flacso-admin-panel.php');

function panel_links_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

panel_links_assert('' !== $source, 'Debe existir class-flacso-admin-panel.php');

// Pantallas registradas con add_menu_page / add_submenu_page que el Resumen puede enlazar.
$registered = array('flacso-consultas', 'flacso-correos', 'flacso-integracion-meta', 'flacso-main-page', 'flacso-sistema', 'flacso-panel');

preg_match_all('#admin\.php\?page=([a-zA-Z0-9_-]+)#', $source, $matches);
foreach (array_unique($matches[1]) as $slug) {
    panel_links_assert(in_array($slug, $registered, true), "El Resumen enlaza a la pantalla inexistente: admin.php?page={$slug}");
}

panel_links_assert(strpos($source, "edit.php?post_type=docente") !== false, 'La tarjeta Docentes debe abrir el listado nativo de Personas / Equipo');

echo "OK Resumen FLACSO: sin enlaces a pantallas inexistentes\n";
