<?php

$root = dirname(__DIR__);
$team_file = $root . '/modules/oferta-academica/includes/class-academic-team-editor.php';
$offer_editor_file = $root . '/modules/oferta-academica/includes/class-oferta-admin-fields.php';
$cohort_file = $root . '/modules/oferta-academica/includes/class-cohorte.php';
$init_file = $root . '/modules/oferta-academica/init.php';

function team_scope_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

team_scope_assert(file_exists($team_file), 'debe existir el editor de equipos académicos sin colisiones');
$team = (string) file_get_contents($team_file);
$offer_editor = (string) file_get_contents($offer_editor_file);
$cohort = (string) file_get_contents($cohort_file);
$init = (string) file_get_contents($init_file);

team_scope_assert(strpos($team, 'final class FLACSO_Academic_Team_Editor') !== false, 'debe usar la clase nueva del editor de equipos');
team_scope_assert(strpos($team, "register_post_meta(FLACSO_Oferta_Academica::POST_TYPE, 'equipo_academico'") !== false, 'Oferta debe registrar equipo_academico');
team_scope_assert(strpos($team, "register_post_meta(FLACSO_Cohorte::POST_TYPE, 'equipos'") !== false, 'Cohorte debe registrar equipos');

team_scope_assert(strpos($team, "TYPE_COORDINATION = 'coordinacion_academica'") !== false, 'Oferta debe distinguir Coordinación académica');
team_scope_assert(strpos($team, "TYPE_CUSTOM = 'personalizado'") !== false, 'Oferta debe distinguir grupos estables personalizados');
team_scope_assert(strpos($team, 'Los datos históricos se muestran como el grupo personalizado') !== false, 'el histórico no debe presumirse Coordinación académica');
team_scope_assert(strpos($team, "'Equipo académico'") !== false, 'el histórico debe conservarse como Equipo académico');

team_scope_assert(strpos($team, 'flacso_oferta_equipos') !== false, 'Oferta debe editar una lista de grupos estables');
team_scope_assert(strpos($team, 'flacso_cohorte_equipos') !== false, 'Cohorte debe editar sus grupos propios');
team_scope_assert(strpos($team, "update_post_meta(\$post_id, 'equipo_academico'") !== false, 'Oferta debe persistir equipo_academico');
team_scope_assert(strpos($team, "update_post_meta(\$post_id, 'equipos'") !== false, 'Cohorte debe persistir equipos');

team_scope_assert(strpos($offer_editor, 'FLACSO_Academic_Team_Editor::render_offer_section($post)') !== false, 'Equipos estables debe integrarse al editor de Oferta');
team_scope_assert(strpos($cohort, 'FLACSO_Academic_Team_Editor::render_cohort_section($post)') !== false, 'Equipos de Cohorte debe integrarse al editor de Cohorte');

team_scope_assert(strpos($team, "add_action('add_meta_boxes'") === false, 'los equipos no deben volver a renderizarse como metaboxes separados');
team_scope_assert(strpos($init, 'class-academic-team-editor.php') !== false, 'init debe cargar el editor nuevo de equipos');
team_scope_assert(strpos($init, 'class-academic-team-admin.php') === false, 'init no debe cargar el editor legado que colisiona');
team_scope_assert(strpos($init, 'FLACSO_Academic_Team_Editor::init();') !== false, 'init debe inicializar el editor nuevo de equipos');

echo "OK academic-team-scope-contract-test\n";
