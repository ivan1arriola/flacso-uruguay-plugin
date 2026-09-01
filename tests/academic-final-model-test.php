<?php

define('ABSPATH', __DIR__ . '/../');

function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_kses_post($value) { return (string) $value; }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function absint($value) { return abs((int) $value); }
function esc_url_raw($value, $protocols = null) { return filter_var((string) $value, FILTER_SANITIZE_URL); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_get_object_terms($id, $taxonomy) { return []; }
function is_wp_error($value) { return false; }

require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-academica.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-cohorte.php';
require_once __DIR__ . '/../modules/seminarios/includes/class-seminario.php';
require_once __DIR__ . '/../modules/seminarios/includes/class-edicion.php';

function final_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

final_assert(
    array_keys(FLACSO_Oferta_Academica::tipos()) === ['doctorado', 'maestria', 'especializacion', 'diplomado', 'diploma'],
    'OfertaAcademica debe tener exactamente cinco tipos, incluida especializacion'
);
final_assert(!FLACSO_Oferta_Academica::tipo_valido('seminario'), 'Seminario no puede ser tipo de OfertaAcademica');
final_assert(FLACSO_Edicion::sanitize_state('cancelada') === 'cancelada', 'estado de edicion válido');
final_assert(FLACSO_Edicion::sanitize_state('inscripciones_abiertas') === 'planificada', 'no persiste estado de inscripción');
final_assert(FLACSO_Cohorte::to_roman(14) === 'XIV', 'algoritmo romano por sustracción ordenada');
final_assert(FLACSO_Cohorte::to_roman(1994) === 'MCMXCIV', 'algoritmo romano aplica pares sustractivos');
final_assert(FLACSO_Cohorte::display_name(10) === 'Cohorte X', 'etiqueta canónica no incluye la oferta');
final_assert(FLACSO_Cohorte::sanitize_registration_url('https://preinscripciones.flacso.edu.uy/oferta/x/') !== '', 'acepta URL del gestor externo');
final_assert(FLACSO_Cohorte::sanitize_registration_url('https://flacso.edu.uy/preinscripcion/') === '', 'rechaza formulario local');

$relations = FLACSO_Oferta_Academica::sanitize_seminars([
    ['seminario_id' => 8, 'orden' => 2, 'caracter' => 'obligatorio', 'creditos_reconocidos' => '4'],
    ['seminario_id' => 8, 'orden' => 1],
]);
final_assert(count($relations) === 1 && $relations[0]['seminario_id'] === 8, 'relaciones de seminario sin duplicados');

// Pruebas de la regla automática de preinscripción de Edicion.
if (!function_exists('get_option')) {
    function get_option($k, $d = false) { return $d; }
}
$GLOBALS['flacso_test_post_meta'] = [];
if (!function_exists('get_post_meta')) {
    function get_post_meta($id, $k, $s = false) {
        return $GLOBALS['flacso_test_post_meta'][$id][$k] ?? '';
    }
}

final_assert(FLACSO_Edicion::get_days_after_start_limit(0) === 10, 'dias de cierre por defecto es 10');

$GLOBALS['flacso_test_post_meta'][999] = [
    'estado' => 'planificada',
];
final_assert(
    FLACSO_Edicion::accepts_registration(999, strtotime('2026-03-01 12:00:00')),
    'una edicion creada sin fecha de inicio queda abierta automaticamente'
);

$GLOBALS['flacso_test_post_meta'][1000] = [
    'estado' => 'finalizada',
    'fecha_inicio' => '2026-03-01',
    'dias_cierre_post_inicio' => 10,
];
final_assert(
    FLACSO_Edicion::accepts_registration(1000, strtotime('2026-03-05 12:00:00')),
    'el estado finalizada no cierra antes del limite temporal'
);
final_assert(
    FLACSO_Edicion::accepts_registration(1000, strtotime('2026-03-11 23:59:59')),
    'permanece abierta hasta el final del dia limite'
);
final_assert(
    !FLACSO_Edicion::accepts_registration(1000, strtotime('2026-03-12 00:00:00')),
    'cierra automaticamente al superar los dias configurados desde el inicio'
);

$GLOBALS['flacso_test_post_meta'][1001] = [
    'estado' => 'cancelada',
    'fecha_inicio' => '2026-12-01',
    'dias_cierre_post_inicio' => 10,
];
final_assert(
    !FLACSO_Edicion::accepts_registration(1001, strtotime('2026-03-01 12:00:00')),
    'una edicion cancelada cierra inmediatamente la preinscripcion'
);

fwrite(STDOUT, "OK academic final model\n");