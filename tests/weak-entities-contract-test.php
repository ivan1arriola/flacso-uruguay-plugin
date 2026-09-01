<?php

$root = dirname(__DIR__);
$cohorte = file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');
$edicion = file_get_contents($root . '/modules/seminarios/includes/class-edicion.php');
$oferta = file_get_contents($root . '/modules/oferta-academica/includes/class-cpt-oferta-academica.php');
$seminario = file_get_contents($root . '/modules/seminarios/includes/class-seminario-cpt.php');
$panel = file_get_contents($root . '/includes/core/class-flacso-admin-panel.php');

function weak_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

// 1. Ocultas del menú suelto
weak_assert(strpos($cohorte, "'show_in_menu' => false") !== false, "Cohorte tiene show_in_menu false");
weak_assert(strpos($edicion, "'show_in_menu' => false") !== false, "Edicion tiene show_in_menu false");
weak_assert(strpos($panel, "hidden_submenus") !== false, "Admin panel oculta submenus de cohortes y ediciones sueltas");

// 2. Oferta Académica gestiona sus Cohortes
weak_assert(strpos($oferta, "flacso_oferta_cohortes_box") !== false, "Oferta Academica tiene metabox de cohortes");
weak_assert(strpos($oferta, "cohortes") !== false, "Oferta Academica tiene columna de cohortes");
weak_assert(
    strpos($oferta, '$estado = FLACSO_Cohorte::sanitize_state(get_post_meta($c->ID, \'estado\', true));') !== false,
    "Metabox de cohortes inicializa el estado antes de mostrarlo"
);

// 3. Seminario gestiona sus Ediciones
weak_assert(strpos($seminario, "flacso_seminario_ediciones_box") !== false, "Seminario tiene metabox de ediciones");
weak_assert(strpos($seminario, "ediciones") !== false, "Seminario tiene columna de ediciones");

// 4. Cohorte y Edición tienen columnas y filtros hacia su entidad padre
weak_assert(strpos($cohorte, "META_PARENT_ID") !== false && strpos($cohorte, "filter_query_by_parent") !== false, "Cohorte tiene filtro y asociacion a padre");
weak_assert(strpos($edicion, "META_PARENT_ID") !== false && strpos($edicion, "filter_query_by_parent") !== false, "Edicion tiene filtro y asociacion a padre");

fwrite(STDOUT, "OK weak entities contract
");
