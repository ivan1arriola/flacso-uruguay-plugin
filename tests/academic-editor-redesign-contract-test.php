<?php

$root = dirname(__DIR__);

$ui = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-academic-admin-ui.php');
$init = (string) file_get_contents($root . '/modules/oferta-academica/init.php');
$program = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-cpt-programa-academico.php');
$offer = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-admin-fields.php');
$offer_model = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-oferta-academica.php');
$cohort = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');
$teams = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-academic-team-editor.php');
$carta = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-offer-carta-contact-admin.php');
$price = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-cpt-tabla-precio.php');

function academic_editor_redesign_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

academic_editor_redesign_assert(
    strpos($init, 'class-academic-admin-ui.php') !== false
        && strpos($init, 'FLACSO_Academic_Admin_UI::init();') !== false,
    'el módulo debe cargar e inicializar el patrón visual compartido'
);

foreach ([
    '<details class="flacso-academic-section"',
    'data-flacso-academic-sections="open"',
    'data-flacso-academic-sections="close"',
    ':focus-visible',
    '@media(max-width:782px)',
    'Sin completar',
] as $needle) {
    academic_editor_redesign_assert(strpos($ui, $needle) !== false, "la UI compartida debe contener {$needle}");
}

foreach (['Identidad y contacto', 'Presentación', 'Coordinación', 'Ofertas vinculadas'] as $section) {
    academic_editor_redesign_assert(strpos($program, $section) !== false, "Programa debe incluir {$section}");
}
academic_editor_redesign_assert(
    strpos($program, 'programa_academico_id') !== false && strpos($program, 'Agregar nueva oferta') !== false,
    'Programa debe permitir alta contextual de ofertas'
);

foreach ([
    'Datos generales',
    'Presentación y objetivos',
    'Cursado y aprobación',
    'Perfiles y requisitos',
    'Titulación, acreditación y financiación',
    'Plan de estudios',
    'Reconocimientos y visualización',
    'Cohortes',
] as $section) {
    academic_editor_redesign_assert(strpos($offer, $section) !== false, "Oferta debe incluir {$section}");
}
academic_editor_redesign_assert(strpos($teams, 'Equipos estables') !== false, 'Oferta debe incluir Equipos estables');
academic_editor_redesign_assert(strpos($carta, 'Contacto de la carta') !== false, 'Oferta debe incluir Contacto de la carta');
academic_editor_redesign_assert(
    strpos($offer_model, "META_PRESENTATION_COLORS = 'colores_presentacion'") !== false,
    'Oferta debe registrar el metadato colores_presentacion'
);
academic_editor_redesign_assert(
    strpos($offer, 'flacso_oferta_color_principal') !== false
        && strpos($offer, 'data-clear-offer-color') !== false
        && strpos($offer, 'Por ahora no modifica la página pública') !== false,
    'Oferta debe permitir elegir, previsualizar y limpiar el color sin aplicarlo públicamente'
);

foreach (['Oferta padre', 'Estado y aranceles', 'Comienzo', 'Cursado', 'Preinscripción', 'Enlaces útiles'] as $section) {
    academic_editor_redesign_assert(strpos($cohort, $section) !== false, "Cohorte debe incluir {$section}");
}
academic_editor_redesign_assert(strpos($teams, 'Equipos de la cohorte') !== false, 'Cohorte debe incluir Equipos de la cohorte');
$render_start = strpos($cohort, 'public static function render_meta_box');
$render_end = strpos($cohort, 'public static function render_start_date_preview_script', $render_start);
$cohort_render = $render_start === false ? '' : substr($cohort, $render_start, $render_end - $render_start);
academic_editor_redesign_assert(
    strpos($cohort_render, 'name="fecha_fin"') === false
        && strpos($cohort_render, 'name="anio_fin"') === false,
    'Cohorte no debe volver a pedir fecha o año de fin'
);
academic_editor_redesign_assert(
    strpos($offer, 'FLACSO_Academic_Team_Editor::render_offer_section($post)') !== false
        && strpos($offer, 'FLACSO_Offer_Carta_Contact_Admin::render_section($post)') !== false,
    'Oferta debe integrar equipos estables y contacto de carta dentro del editor principal'
);
academic_editor_redesign_assert(
    strpos($cohort, 'FLACSO_Academic_Team_Editor::render_cohort_section($post)') !== false,
    'Cohorte debe integrar sus equipos variables dentro del editor principal'
);
academic_editor_redesign_assert(
    strpos($cohort, 'flacso-cohorte-start-preview') !== false
        && strpos($cohort, 'get_permalink($parent_id)') !== false
        && strpos($cohort, 'get_edit_post_link($parent_id)') !== false,
    'Cohorte debe preservar previsualización y enlaces separados a la Oferta padre'
);

foreach (['Identificación', 'Filas de precios', 'Nota', 'Usos vinculados'] as $section) {
    academic_editor_redesign_assert(strpos($price, $section) !== false, "Tabla de Aranceles debe incluir {$section}");
}
foreach (['data-add-price-row', 'data-remove-price-row', 'flacso_price_featured', 'data-empty-state', 'FLACSO_Price_Table_Repository::linked_uses'] as $needle) {
    academic_editor_redesign_assert(strpos($price, $needle) !== false, "Tabla de Aranceles debe preservar {$needle}");
}

echo "OK academic editor redesign contract\n";
