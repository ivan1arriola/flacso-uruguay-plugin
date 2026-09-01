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

// Pruebas del estado explícito de preinscripción de Edición.
if (!function_exists('get_option')) {
    function get_option($k, $d = false) { return $d; }
}
$GLOBALS['flacso_test_post_meta'] = [];
if (!function_exists('get_post_meta')) {
    function get_post_meta($id, $k, $s = false) {
        return $GLOBALS['flacso_test_post_meta'][$id][$k] ?? '';
    }
}
if (!function_exists('metadata_exists')) {
    function metadata_exists($type, $id, $key) {
        return array_key_exists($key, $GLOBALS['flacso_test_post_meta'][$id] ?? []);
    }
}
if (!function_exists('rest_sanitize_boolean')) {
    function rest_sanitize_boolean($value) {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

final_assert(FLACSO_Edicion::get_days_after_start_limit(0) === 10, 'dias de cierre por defecto es 10');

$GLOBALS['flacso_test_post_meta'][998] = [
    'estado' => 'planificada',
];
final_assert(
    FLACSO_Cohorte::accepts_registration(998, strtotime('2026-03-01 12:00:00')),
    'una cohorte sin booleano conserva la apertura legacy durante el rollout'
);
$GLOBALS['flacso_test_post_meta'][998]['preinscripcion_habilitada'] = false;
final_assert(
    !FLACSO_Cohorte::accepts_registration(998, strtotime('2026-03-01 12:00:00')),
    'el cierre explícito prevalece sobre la regla legacy de cohorte'
);
$GLOBALS['flacso_test_post_meta'][998] = [
    'estado' => 'finalizada',
    'preinscripcion_habilitada' => true,
];
final_assert(
    FLACSO_Cohorte::accepts_registration(998, strtotime('2026-03-01 12:00:00')),
    'el booleano explícito queda separado del estado académico de cohorte'
);

$GLOBALS['flacso_test_post_meta'][999] = [
    'estado' => 'planificada',
];
final_assert(
    FLACSO_Edicion::accepts_registration(999),
    'una edición sin booleano conserva la apertura legacy durante el rollout'
);

$GLOBALS['flacso_test_post_meta'][1000] = [
    'estado' => 'planificada',
    'preinscripcion_habilitada' => true,
];
final_assert(
    FLACSO_Edicion::accepts_registration(1000),
    'una edición planificada puede tener preinscripciones abiertas'
);

$GLOBALS['flacso_test_post_meta'][1000]['preinscripcion_habilitada'] = false;
final_assert(
    !FLACSO_Edicion::accepts_registration(1000),
    'el booleano explícito cierra la preinscripción'
);

$GLOBALS['flacso_test_post_meta'][1000] = [
    'estado' => 'finalizada',
    'preinscripcion_habilitada' => true,
];
final_assert(
    FLACSO_Edicion::accepts_registration(1000),
    'el estado académico finalizada no sobrescribe el booleano de preinscripción'
);

$GLOBALS['flacso_test_post_meta'][1001] = [
    'estado' => 'cancelada',
];
final_assert(
    !FLACSO_Edicion::accepts_registration(1001),
    'una edición legacy cancelada permanece cerrada'
);

$GLOBALS['flacso_test_post_meta'][1001]['preinscripcion_habilitada'] = true;
final_assert(
    FLACSO_Edicion::accepts_registration(1001),
    'el booleano explícito prevalece sobre la regla legacy'
);

fwrite(STDOUT, "OK academic final model\n");
