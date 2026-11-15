<?php

$root = dirname(__DIR__);
$panel = file_get_contents($root . '/includes/core/class-flacso-admin-panel.php');
$styles = file_get_contents($root . '/includes/assets/flacso-admin-panel.css');
$main = file_get_contents($root . '/flacso-uruguay.php');
$homepage_admin = file_get_contents($root . '/modules/main-page/includes/class-flacso-main-page-admin.php');

function panel_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

panel_assert(strpos($main, 'class-flacso-admin-panel.php') !== false, 'el plugin carga el panel');
panel_assert(strpos($panel, "public const PAGE_SLUG = 'flacso-panel'") !== false, 'el panel tiene slug canónico');
panel_assert(strpos($panel, 'Programa') !== false && strpos($panel, 'Cohorte') !== false, 'muestra el flujo de ofertas');
panel_assert(strpos($panel, 'Seminario') !== false && strpos($panel, 'Edición') !== false, 'muestra el flujo de seminarios');
panel_assert(strpos($panel, 'Calidad de datos') !== false, 'incluye control de integridad');
panel_assert(strpos($homepage_admin, "add_menu_page(") === false, 'Portada no crea un segundo Panel FLACSO');
panel_assert(strpos($panel, "'parent' => 'site-name'") !== false, 'la barra superior reutiliza el menú nativo del sitio');
panel_assert(strpos($panel, "'id'    => 'flacso-panel'") === false, 'la barra superior no crea un segundo menú FLACSO');
panel_assert(substr_count($panel, "admin_bar->add_node") >= 2, 'la barra superior conserva accesos de gestión dentro del menú del sitio');
panel_assert(strpos($styles, '@media (max-width: 782px)') !== false, 'el panel tiene diseño móvil');

foreach (['programa-academico', 'oferta-academica', 'cohorte', 'seminario', 'edicion'] as $post_type) {
    panel_assert(strpos($panel, "'{$post_type}'") !== false, "el panel enlaza {$post_type}");
}

// Menú nativo de WordPress: sin raíz FLACSO universal ni reagrupación de terceros.
panel_assert(!file_exists($root . '/includes/core/class-flacso-admin-menu-organizer.php'), 'no existe el organizador que reubica menús ajenos');
panel_assert(strpos($main, 'admin-menu-organizer') === false, 'el plugin no carga el organizador');
panel_assert(preg_match("/add_submenu_page\(\s*'index\.php'/", $panel) === 1, 'el resumen FLACSO vive bajo Escritorio');
panel_assert(strpos($panel, "admin.php?page=' . self::PAGE_SLUG") !== false, 'el panel conserva su URL canónica');
panel_assert(strpos($panel, "if (!FLACSO_Academic_Assistant::is_assistant())") !== false, 'la barra superior sólo añade accesos para la Asistente');

// Ninguna pantalla ni CPT queda colgada del antiguo menú raíz.
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = str_replace(DIRECTORY_SEPARATOR, '/', (string) $file);
    if (substr($path, -4) !== '.php' || strpos($path, '/tests/') !== false || strpos($path, '/vendor/') !== false || strpos($path, '/node_modules/') !== false || strpos($path, '/.worktrees/') !== false || strpos($path, '/build/') !== false) {
        continue;
    }
    $code = (string) file_get_contents($path);
    if (strpos($path, 'class-flacso-admin-panel.php') !== false) {
        continue;
    }
    panel_assert(
        !preg_match('/(add_submenu_page\(\s*|show_in_menu\'\s*=>\s*)FLACSO_Admin_Panel::PAGE_SLUG/', $code),
        'nada cuelga del menú raíz FLACSO: ' . basename($path)
    );
}

fwrite(STDOUT, "OK FLACSO admin panel contract\n");
