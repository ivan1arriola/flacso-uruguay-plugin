<?php

define('ABSPATH', __DIR__ . '/../');

function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_kses_post($value) { return (string) $value; }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function absint($value) { return abs((int) $value); }
function wp_get_object_terms($id, $taxonomy) { return []; }
function is_wp_error($value) { return false; }

require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-academica.php';
require_once __DIR__ . '/../modules/seminarios/includes/class-seminario.php';
require_once __DIR__ . '/../modules/seminarios/includes/class-edicion-seminario.php';

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
final_assert(FLACSO_Edicion_Seminario::sanitize_state('cancelada') === 'cancelada', 'estado de edicion válido');
final_assert(FLACSO_Edicion_Seminario::sanitize_state('inscripciones_abiertas') === 'planificada', 'no persiste estado de inscripción');

$relations = FLACSO_Oferta_Academica::sanitize_seminars([
    ['seminario_id' => 8, 'orden' => 2, 'caracter' => 'obligatorio', 'creditos_reconocidos' => '4'],
    ['seminario_id' => 8, 'orden' => 1],
]);
final_assert(count($relations) === 1 && $relations[0]['seminario_id'] === 8, 'relaciones de seminario sin duplicados');

fwrite(STDOUT, "OK academic final model\n");
