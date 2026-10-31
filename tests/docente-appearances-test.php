<?php
// Índice de apariciones: cada contenido publicado cuenta una vez por persona.
define('ABSPATH', __DIR__ . '/');
function absint($v) { return abs((int) $v); }
function __($t, $d = '') { return $t; }
function check($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
require dirname(__DIR__) . '/modules/docentes/includes/class-docente-appearances.php';

// Equipos con la forma de equipo_academico / equipos.
$groups = [
    ['nombre' => 'Coordinación', 'docentes' => [['id' => 7, 'rol' => 'Coordinadora'], ['id' => '8']]],
    ['nombre' => 'Docentes', 'docentes' => [['id' => 7], ['id' => 0], ['sin_id' => true]]],
    'basura',
];
check(Docente_Appearances::team_member_ids($groups) === [7, 8, 7], 'Lee los integrantes de todos los grupos y descarta los inválidos');
check(Docente_Appearances::team_member_ids('no es un arreglo') === [], 'Un valor no válido no produce integrantes');

$authorities = ['direccion' => [['docente_id' => 7, 'nombre_manual' => ''], ['docente_id' => 0]], 'equipo' => ['personas' => [['docente_id' => '9']]]];
check(Docente_Appearances::authority_ids($authorities) === [7, 9], 'Encuentra docente_id en cualquier sección de autoridades');
check(Docente_Appearances::authority_ids(false) === [], 'Sin datos de autoridades no hay apariciones');

$index = Docente_Appearances::tally([
    ['kind' => 'oferta', 'title' => 'Maestría A', 'people' => [7, 8, 7]],
    ['kind' => 'oferta', 'title' => 'Diploma B', 'people' => [7]],
    ['kind' => 'cohorte', 'title' => 'Maestría A · Cohorte II', 'people' => ['7', 9]],
    ['kind' => 'edicion', 'title' => 'Seminario X · 2026', 'people' => [8]],
    ['kind' => 'autoridades', 'title' => 'Página de autoridades', 'people' => [7]],
]);
check($index[7]['total'] === 4, 'Una persona repetida dentro de un mismo contenido cuenta una vez');
check($index[7]['kinds'] === ['oferta' => 2, 'cohorte' => 1, 'autoridades' => 1], 'Cuenta por tipo de contenido');
check($index[7]['titles'] === ['Maestría A', 'Diploma B', 'Maestría A · Cohorte II', 'Página de autoridades'], 'Conserva los títulos para el detalle');
check($index[8]['total'] === 2 && $index[9]['total'] === 1, 'Cuenta a cada persona por separado');
check(!isset($index[10]), 'Quien no figura en ningún contenido no aparece en el índice');
check(array_keys(Docente_Appearances::kinds()) === ['oferta', 'cohorte', 'edicion', 'autoridades'], 'Los tipos se presentan en un orden estable');
check(Docente_Appearances::kinds()['oferta'] === ['oferta', 'ofertas'], 'Cada tipo tiene singular y plural');
echo "OK docente appearances\n";
