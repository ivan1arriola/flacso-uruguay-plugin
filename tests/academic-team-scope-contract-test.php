<?php

$root = dirname(__DIR__);
$team_file = $root . '/modules/oferta-academica/includes/class-academic-team-editor.php';
$init_file = $root . '/modules/oferta-academica/init.php';

function team_scope_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

team_scope_assert(file_exists($team_file), 'debe existir el editor de equipos académicos sin colisiones');
$team = (string) file_get_contents($team_file);
$init = (string) file_get_contents($init_file);

team_scope_assert(strpos($team, 'final class FLACSO_Academic_Team_Editor') !== false, 'debe usar la clase nueva del editor de equipos');
team_scope_assert(strpos($team, "register_post_meta(FLACSO_Oferta_Academica::POST_TYPE, 'equipo_academico'") !== false, 'Oferta debe registrar equipo_academico');
team_scope_assert(strpos($team, "register_post_meta(FLACSO_Cohorte::POST_TYPE, 'equipos'") !== false, 'Cohorte debe registrar equipos');
team_scope_assert(strpos($team, "'post_type' => 'docente'") !== false, 'todos los grupos deben seleccionar desde el CPT docente');
team_scope_assert(strpos($team, 'flacso_equipo_academico[docentes]') !== false, 'Oferta debe editar integrantes del Equipo académico');
team_scope_assert(strpos($team, 'flacso_cohorte_equipos') !== false, 'Cohorte debe editar grupos propios');
team_scope_assert(strpos($team, "update_post_meta(\$post_id, 'equipo_academico'") !== false, 'Oferta debe persistir equipo_academico');
team_scope_assert(strpos($team, "update_post_meta(\$post_id, 'equipos'") !== false, 'Cohorte debe persistir equipos');
team_scope_assert(strpos($init, 'class-academic-team-editor.php') !== false, 'init debe cargar el editor nuevo de equipos');
team_scope_assert(strpos($init, 'class-academic-team-admin.php') === false, 'init no debe cargar el editor legado que colisiona');
team_scope_assert(strpos($init, 'FLACSO_Academic_Team_Editor::init();') !== false, 'init debe inicializar el editor nuevo de equipos');

echo "OK academic-team-scope-contract-test\n";
