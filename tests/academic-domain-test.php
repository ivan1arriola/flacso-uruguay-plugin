<?php

define('ABSPATH', __DIR__ . '/../');

$GLOBALS['domain_posts'] = [
    1 => (object) ['ID' => 1, 'post_type' => 'oferta-academica', 'post_title' => 'Seminario'],
    2 => (object) ['ID' => 2, 'post_type' => 'oferta-academica', 'post_title' => 'Diploma'],
    11 => (object) ['ID' => 11, 'post_type' => 'instancia-oferta', 'post_title' => ''],
    12 => (object) ['ID' => 12, 'post_type' => 'instancia-oferta', 'post_title' => 'Cohorte XII'],
];
$GLOBALS['domain_meta'] = [
    11 => [
        'oferta_academica_id' => 1,
        'estado' => 'planificada',
        'preinscripcion_apertura' => '2026-08-01T10:00:00+00:00',
    ],
    12 => [
        'oferta_academica_id' => 2,
        'estado' => 'en_curso',
        'preinscripcion_apertura' => '2026-08-01T10:00:00+00:00',
    ],
];
$GLOBALS['domain_types'] = [1 => 'seminario', 2 => 'diploma'];

function __($text, $domain = null) { return $text; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
function rest_sanitize_boolean($value) { return filter_var($value, FILTER_VALIDATE_BOOLEAN); }
function get_post($id) { return $GLOBALS['domain_posts'][(int) $id] ?? null; }
function get_post_type($id) { return get_post($id)->post_type ?? null; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['domain_meta'][(int) $id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['domain_meta'][(int) $id][$key] = $value; }
function wp_get_object_terms($id, $taxonomy) { return [(object) ['slug' => $GLOBALS['domain_types'][(int) $id] ?? '']]; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function remove_accents($value) { return strtr($value, ['í' => 'i', 'ó' => 'o', 'é' => 'e', 'á' => 'a', 'ú' => 'u', 'ñ' => 'n']); }
function get_posts($args) { return []; }

class WP_Error {}

require_once __DIR__ . '/../modules/instancias-oferta/includes/class-preinscription-flow.php';
require_once __DIR__ . '/../modules/instancias-oferta/includes/class-instancia-oferta.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-academica.php';

function domain_assert_same($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "Fallo {$label}: esperado " . var_export($expected, true) . ', obtuve ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

domain_assert_same(
    ['doctorado', 'maestria', 'especializacion', 'diplomado', 'diploma', 'seminario'],
    array_keys(FLACSO_Oferta_Academica::tipos()),
    'seis tipos de OfertaAcademica'
);
domain_assert_same(true, FLACSO_Oferta_Academica::tipo_valido('doctorado'), 'doctorado soportado');
domain_assert_same('Edición', FLACSO_Oferta_Academica::etiqueta_instancia(1), 'seminario usa etiqueta Edicion');
domain_assert_same('Cohorte', FLACSO_Oferta_Academica::etiqueta_instancia(2), 'otras ofertas usan Cohorte');
domain_assert_same('Edición', FLACSO_Instancia_Oferta::get_nombre_visible(11), 'nombre visible default nullable');
domain_assert_same('Cohorte XII', FLACSO_Instancia_Oferta::get_nombre_visible(12), 'nombre explicito prevalece');
domain_assert_same(
    ['planificada', 'en_curso', 'finalizada', 'cancelada'],
    FLACSO_Instancia_Oferta::estados(),
    'estado academico separado'
);
domain_assert_same('planificada', FLACSO_Instancia_Oferta::normalize_academic_state('preinscripciones_abiertas'), 'estado legacy no persiste apertura');
domain_assert_same('2026-08-08T10:00:00+00:00', FLACSO_Instancia_Oferta::get_preinscripcion_cierre_efectivo(11), 'seminario cierra a siete dias');
domain_assert_same(true, FLACSO_Instancia_Oferta::acepta_preinscripciones(11, '2026-08-08T09:59:59+00:00'), 'seminario abierto antes del cierre');
domain_assert_same(false, FLACSO_Instancia_Oferta::acepta_preinscripciones(11, '2026-08-08T10:00:00+00:00'), 'seminario cerrado al cumplirse siete dias');
domain_assert_same(null, FLACSO_Instancia_Oferta::get_preinscripcion_cierre_efectivo(12), 'no seminario sin cierre manual sigue abierto');
domain_assert_same(true, FLACSO_Instancia_Oferta::acepta_preinscripciones(12, '2030-01-01T00:00:00+00:00'), 'no seminario permanece abierto');
$GLOBALS['domain_meta'][12]['preinscripcion_cierre_manual'] = '2026-09-01T00:00:00+00:00';
domain_assert_same(false, FLACSO_Instancia_Oferta::acepta_preinscripciones(12, '2026-09-01T00:00:00+00:00'), 'cierre manual funciona');
domain_assert_same('derechos de infancia ciudadania digital oportunidades y desafios', FLACSO_Oferta_Academica::normalize_identity_title(' Seminario Derechos de infancia, ciudadanía digital: oportunidades y desafíos. '), 'normalizacion diagnostica');

fwrite(STDOUT, "OK academic domain\n");
