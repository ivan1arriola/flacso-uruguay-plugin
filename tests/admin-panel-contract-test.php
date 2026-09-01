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
panel_assert(strpos($styles, '@media (max-width: 782px)') !== false, 'el panel tiene diseño móvil');

foreach (['programa-academico', 'oferta-academica', 'cohorte', 'seminario', 'edicion', 'tabla-precio'] as $post_type) {
    panel_assert(strpos($panel, "edit.php?post_type={$post_type}") !== false, "falta acceso a {$post_type}");
}

fwrite(STDOUT, "OK FLACSO admin panel contract\n");
