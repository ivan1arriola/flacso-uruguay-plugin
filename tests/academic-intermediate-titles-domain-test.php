<?php
/**
 * Un titulo intermedio es una Oferta de nivel inferior, con menciones
 * opcionales del catalogo de menciones de esa misma Oferta.
 */
if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__) . '/');
function absint($v) { return abs((int) $v); }
function sanitize_key($v) { return strtolower((string) $v); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function get_post_type($id) { return $id === 19 ? 'seminario' : (isset($GLOBALS['types'][$id]) ? 'oferta-academica' : null); }
function is_wp_error($v) { return false; }
function wp_get_object_terms($id, $tax) {
    $type = $GLOBALS['types'][$id] ?? null;
    return $type ? [(object) ['slug' => $type]] : [];
}
function get_post_meta($id, $key, $single = true) {
    return $GLOBALS['mentions'][$id][$key] ?? [];
}
$GLOBALS['types'] = [
    10 => 'maestria', 11 => 'especializacion', 12 => 'diplomado',
    13 => 'diploma', 14 => 'doctorado', 15 => 'maestria',
];
$GLOBALS['mentions'] = [
    11 => ['menciones' => ['Gestión educativa', 'Investigación']],
    12 => ['menciones' => ['Educación']],
    13 => ['menciones' => []],
];
require_once dirname(__DIR__) . '/modules/oferta-academica/includes/class-oferta-academica.php';

function verify_intermediate(bool $value, string $text): void {
    if (!$value) { fwrite(STDERR, "FAIL: {$text}\n"); exit(1); }
}

verify_intermediate(FLACSO_Oferta_Academica::intermediate_level('maestria') === 4, 'maestria nivel 4');
verify_intermediate(FLACSO_Oferta_Academica::intermediate_level('especializacion') === 3, 'especializacion nivel 3');
verify_intermediate(FLACSO_Oferta_Academica::intermediate_level('diplomado') === 2, 'diplomado nivel 2');
verify_intermediate(FLACSO_Oferta_Academica::intermediate_level('diploma') === 1, 'diploma nivel 1');
verify_intermediate(FLACSO_Oferta_Academica::intermediate_level('doctorado') === 0, 'doctorado fuera de esta secuencia');

$raw = [
    ['oferta_id' => 11, 'menciones' => ['Gestión educativa', 'Inventada', 'Gestión educativa']],
    ['oferta_id' => 11, 'menciones' => ['Investigación']],
    ['oferta_id' => 12, 'menciones' => []],
    ['oferta_id' => 13],
    ['oferta_id' => 15],
    ['oferta_id' => 14],
    ['oferta_id' => 19],
    ['oferta_id' => 10],
    ['oferta_id' => 999],
];
$result = FLACSO_Oferta_Academica::sanitize_intermediate_titles($raw, 10);
verify_intermediate(count($result) === 3, 'solo tres destinos inferiores, sin duplicados');
verify_intermediate($result[0]['oferta_id'] === 11, 'destino especializacion');
verify_intermediate($result[0]['menciones'] === ['Gestión educativa'], 'filtra menciones ajenas y duplicadas');
verify_intermediate($result[1] === ['oferta_id' => 12, 'menciones' => []], 'un diplomado puede ir sin mencion');
verify_intermediate($result[2] === ['oferta_id' => 13, 'menciones' => []], 'un diploma puede ir sin mencion');
verify_intermediate(FLACSO_Oferta_Academica::sanitize_intermediate_titles($raw, 11) === [
    ['oferta_id' => 12, 'menciones' => []],
    ['oferta_id' => 13, 'menciones' => []],
], 'especializacion solo vincula diplomado y diploma');
verify_intermediate(FLACSO_Oferta_Academica::sanitize_intermediate_titles($raw, 13) === [], 'diploma no tiene titulaciones inferiores');
verify_intermediate(FLACSO_Oferta_Academica::sanitize_intermediate_titles($raw, 14) === [], 'doctorado no se incluye en jerarquia especificada');
verify_intermediate(FLACSO_Oferta_Academica::sanitize_intermediate_titles(['titulo' => 'cadena'], 10) === [], 'no persiste texto libre');
verify_intermediate(FLACSO_Oferta_Academica::sanitize_intermediate_titles([], 10) === [], 'sin titulos es valido');

$admin = file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/includes/class-oferta-admin-fields.php');
verify_intermediate(strpos($admin, 'self::intermediate_titles_field($post->ID)') !== false, 'editor usa relaciones');
verify_intermediate(strpos($admin, "self::list_field(\$post->ID, 'titulos_intermedios'") === false, 'elimina editor texto libre');
verify_intermediate(strpos($admin, 'flacso_oferta_titulos_intermedios_present') !== false, 'permite vaciar todas las relaciones');
verify_intermediate(strpos($admin, 'data-intermediate-mentions') !== false, 'menciones opcionales por destino');
verify_intermediate(strpos($admin, 'sanitize_intermediate_titles($raw_titles, $post_id)') !== false, 'valida al guardar');
verify_intermediate(strpos($admin, 'data-intermediate-select') !== false, 'selector de Ofertas disponible');
echo "OK intermediate title relation, hierarchy and optional mentions\n";
