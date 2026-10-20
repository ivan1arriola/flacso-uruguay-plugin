<?php
/**
 * Test funcional aislado del modelo de articulación.
 * No toca preinscripciones, cohortes ni facturación.
 */
define('ABSPATH', dirname(__DIR__) . '/');
class WP_Error {
    private $code;
    private $message;
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
class FLACSO_Oferta_Academica {
    public const POST_TYPE = 'oferta-academica';
    public static function sanitize_text_list($items) {
        return is_array($items) ? array_values(array_filter(array_map('strval', $items))) : [];
    }
}
function __($text, $domain = '') { return $text; }
function absint($v) { return abs((int) $v); }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $v)); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function wp_kses_post($v) { return strip_tags((string) $v, '<p><strong><em>'); }
function wp_strip_all_tags($v) { return strip_tags((string) $v); }
function esc_url_raw($v, $allowed = []) {
    $url = trim((string) $v);
    return strpos($url, 'https://') === 0 ? $url : '';
}
function get_post_type($id) {
    return isset($GLOBALS['offers'][$id]) ? 'oferta-academica' : 'other';
}
function get_post_status($id) {
    return $GLOBALS['offers'][$id]['status'] ?? 'draft';
}
function get_post_meta($id, $key, $single = true) {
    return $GLOBALS['metadata'][$id][$key] ?? [];
}
function get_the_title($id) { return $GLOBALS['offers'][$id]['name'] ?? ''; }
function get_permalink($id) { return 'https://flacso.edu.uy/oferta/' . $id; }
function get_posts($args) {
    if (($args['post_type'] ?? '') !== 'articulacion-academica') return [];
    $expected = $args['meta_query'][0]['value'] ?? '';
    $index = $GLOBALS['metadata'][555]['_flacso_articulacion_ofertas_index'] ?? '';
    return strpos($index, $expected) !== false ? [(object)['ID'=>555,'post_title'=>'Plan de Género']] : [];
}
function is_wp_error($v) { return $v instanceof WP_Error; }
$GLOBALS['offers'] = [
    10 => ['status'=>'publish','name'=>'Diploma en Género'],
    20 => ['status'=>'publish','name'=>'Diplomado'],
    21 => ['status'=>'publish','name'=>'Especialización'],
    30 => ['status'=>'publish','name'=>'Maestría en Género'],
    90 => ['status'=>'draft','name'=>'Otro diploma'],
];
$GLOBALS['metadata'] = [
    10 => ['menciones'=>['Mención A','Mención B']],
    20 => ['menciones'=>[]], 21 => ['menciones'=>[]], 30 => ['menciones'=>[]],
];
require dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-articulations.php';
function acheck($value, $label) {
    if (!$value) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
}
$nodes = [
 ['clave'=>'c1','ciclo'=>1,'nombre'=>'Ciclo 1','oferta_id'=>10,'mencion'=>'','calendario_url'=>'https://example.org/c1.pdf'],
 ['clave'=>'c2a','ciclo'=>2,'nombre'=>'Ciclo 2A','oferta_id'=>20,'mencion'=>''],
 ['clave'=>'c2b','ciclo'=>2,'nombre'=>'Ciclo 2B','oferta_id'=>21,'mencion'=>''],
 ['clave'=>'c3','ciclo'=>3,'nombre'=>'Ciclo 3','oferta_id'=>30,'mencion'=>''],
];
$edges = [
 ['origen'=>'c1','destino'=>'c2a','mencion'=>'Mención A'],
 ['origen'=>'c1','destino'=>'c2b','mencion'=>'Mención B'],
 ['origen'=>'c2a','destino'=>'c3','mencion'=>''],
 ['origen'=>'c2b','destino'=>'c3','mencion'=>''],
];
$notes = [
 ['oferta_id'=>10,'introduccion'=>'Ingreso por Diploma','continuidad'=>'Continuación contratada aparte'],
 ['oferta_id'=>30,'introduccion'=>'Ingreso por Maestría','continuidad'=>'Plan de cuotas de tres ciclos'],
];
$input = ['etapas'=>$nodes, 'transiciones'=>$edges, 'presentaciones'=>$notes];
$valid = FLACSO_Academic_Articulations::validate($input);
acheck(!is_wp_error($valid), 'DAG válido con ramificación y convergencia');
acheck(count($valid['etapas']) === 4 && count($valid['transiciones']) === 4, 'etapas y aristas');
acheck($valid['transiciones'][0]['mencion'] === 'Mención A', 'mención habilitante');
acheck($valid['etapas'][0]['calendario_url'] === 'https://example.org/c1.pdf', 'calendario específico');
acheck(count($valid['presentaciones']) === 2, 'textos por oferta');
acheck(FLACSO_Academic_Articulations::offer_index($valid['etapas']) === '|10||20||21||30|', 'índice exacto de ofertas');
acheck(strpos(FLACSO_Academic_Articulations::offer_index($valid['etapas']), '|2|') === false, 'no produce falsos positivos por ID parcial');

$test = $input;
$test['transiciones'][0]['mencion'] = 'No existe';
acheck(is_wp_error(FLACSO_Academic_Articulations::validate($test)), 'rechaza mención ajena');
$test = $input;
$test['transiciones'][2]['destino'] = 'c1';
acheck(is_wp_error(FLACSO_Academic_Articulations::validate($test)), 'rechaza ciclo hacia atrás');
$test = $input;
$test['transiciones'][0]['destino'] = 'c3';
acheck(is_wp_error(FLACSO_Academic_Articulations::validate($test)), 'rechaza saltar ciclo inmediato');
$test = $input;
$test['transiciones'] = [$edges[0], $edges[2]];
acheck(is_wp_error(FLACSO_Academic_Articulations::validate($test)), 'cada etapa superior requiere precedente');
$test = $input;
$test['etapas'][1]['clave'] = 'c1';
acheck(is_wp_error(FLACSO_Academic_Articulations::validate($test)), 'rechaza claves duplicadas');
$test = $input;
$test['etapas'][0]['mencion'] = 'Falsa';
acheck(is_wp_error(FLACSO_Academic_Articulations::validate($test)), 'rechaza mención de título inválida');
$test = $input;
$test['etapas'][0]['oferta_id'] = 999;
acheck(is_wp_error(FLACSO_Academic_Articulations::validate($test)), 'requiere oferta real');
$test = $input;
$test['presentaciones'][] = $notes[0];
acheck(is_wp_error(FLACSO_Academic_Articulations::validate($test)), 'rechaza dos notas para una oferta');
$test = $input;
$test['presentaciones'][] = ['oferta_id'=>90,'continuidad'=>'texto'];
acheck(is_wp_error(FLACSO_Academic_Articulations::validate($test)), 'rechaza notas para oferta externa');

$GLOBALS['metadata'][555] = [
 FLACSO_Academic_Articulations::META_NODES => $valid['etapas'],
 FLACSO_Academic_Articulations::META_EDGES => $valid['transiciones'],
 FLACSO_Academic_Articulations::META_NOTES => $valid['presentaciones'],
 FLACSO_Academic_Articulations::META_INDEX => FLACSO_Academic_Articulations::offer_index($valid['etapas']),
];
$plans = FLACSO_Academic_Articulations::for_offer(30);
acheck(count($plans) === 1, 'Maestría obtiene su plan');
acheck($plans[0]['presentacion']['continuidad'] === 'Plan de cuotas de tres ciclos', 'texto específico de Maestría');
acheck(count($plans[0]['etapas']) === 4, 'resuelve títulos públicos');
acheck(count($plans[0]['transiciones']) === 4, 'resuelve condiciones de continuidad');
acheck(FLACSO_Academic_Articulations::for_offer(10)[0]['presentacion']['continuidad'] === 'Continuación contratada aparte', 'texto específico de Diploma');
acheck(FLACSO_Academic_Articulations::for_offer(90) === [], 'no publica planes de ofertas no vinculadas');
$GLOBALS['offers'][21]['status'] = 'draft';
$visible = FLACSO_Academic_Articulations::for_offer(30);
acheck(count($visible[0]['etapas']) === 3 && count($visible[0]['transiciones']) === 2, 'no expone títulos no publicados ni sus aristas');

$init = file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/init.php');
$admin = file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-articulations-admin.php');
acheck(strpos($init, 'FLACSO_Academic_Articulations::register();') !== false, 'CPT registrado');
acheck(strpos($admin, 'wp_verify_nonce') !== false && strpos($admin, "current_user_can('edit_post',") !== false, 'protege guardado');
acheck(strpos($admin, 'save_post_') !== false, 'guarda desde WordPress');
acheck(strpos($init, 'FLACSO_Academic_Articulations_Admin::init();') !== false, 'editor cargado');
acheck(strpos($init, 'preinscripcion') !== false, 'preinscripción existente intacta');
echo "OK: academic articulation DAG, mentions, publishing and per-offer notes\n";
