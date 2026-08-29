<?php

define('ABSPATH', __DIR__ . '/../');

$GLOBALS['flacso_posts'] = [
    26595 => (object) [
        'ID' => 26595,
        'post_type' => 'oferta-academica',
        'post_status' => 'publish',
        'post_title' => 'Diploma de prueba',
        'post_name' => 'diploma-prueba',
        'post_password' => '',
        'post_modified_gmt' => '2026-08-28 12:00:00',
    ],
    81 => (object) [
        'ID' => 81,
        'post_type' => 'instancia-oferta',
        'post_status' => 'publish',
        'post_title' => 'Cohorte 2027',
        'post_name' => '',
        'post_password' => '',
        'post_modified_gmt' => '2026-08-28 12:00:00',
    ],
    82 => (object) [
        'ID' => 82,
        'post_type' => 'instancia-oferta',
        'post_status' => 'publish',
        'post_title' => 'Cohorte 2026',
        'post_name' => '',
        'post_password' => '',
        'post_modified_gmt' => '2026-08-27 12:00:00',
    ],
    83 => (object) [
        'ID' => 83,
        'post_type' => 'instancia-oferta',
        'post_status' => 'publish',
        'post_title' => 'Cohorte futura',
        'post_name' => '',
        'post_password' => '',
        'post_modified_gmt' => '2026-08-26 12:00:00',
    ],
    26596 => (object) [
        'ID' => 26596,
        'post_type' => 'oferta-academica',
        'post_status' => 'publish',
        'post_title' => 'Diploma cerrado',
        'post_name' => 'diploma-cerrado',
        'post_password' => '',
        'post_modified_gmt' => '2026-08-28 12:00:00',
    ],
    84 => (object) [
        'ID' => 84,
        'post_type' => 'instancia-oferta',
        'post_status' => 'publish',
        'post_title' => 'Cohorte cerrada',
        'post_name' => '',
        'post_password' => '',
        'post_modified_gmt' => '2026-08-28 12:00:00',
    ],
    26597 => (object) [
        'ID' => 26597,
        'post_type' => 'oferta-academica',
        'post_status' => 'publish',
        'post_title' => 'Seminario de prueba',
        'post_name' => 'seminario-prueba',
        'post_password' => '',
        'post_modified_gmt' => '2026-08-29 12:00:00',
    ],
    85 => (object) [
        'ID' => 85,
        'post_type' => 'instancia-oferta',
        'post_status' => 'publish',
        'post_title' => 'Edición 2026',
        'post_name' => '',
        'post_password' => '',
        'post_modified_gmt' => '2026-08-29 12:00:00',
    ],
];
$GLOBALS['flacso_meta'] = [
    26595 => ['correo' => 'programa@flacso.edu.uy'],
    81 => [
        'oferta_academica_id' => 26595,
        'anio' => 2027,
        'semestre' => '1S',
        'numero' => 2,
        'estado' => 'planificada',
        'preinscripcion_apertura' => '2026-08-28T12:00:00+00:00',
        'flujo_preinscripcion' => 'gestor_preinscripciones',
        '_flujo_preinscripcion_bloqueado' => true,
    ],
    82 => [
        'oferta_academica_id' => 26595,
        'anio' => 2026,
        'semestre' => '2S',
        'numero' => 1,
        'estado' => 'planificada',
        'preinscripcion_apertura' => '2026-08-27T12:00:00+00:00',
        'flujo_preinscripcion' => 'legacy_editor',
        '_flujo_preinscripcion_bloqueado' => true,
    ],
    83 => [
        'oferta_academica_id' => 26595,
        'anio' => 2028,
        'semestre' => '1S',
        'numero' => 3,
        'estado' => 'planificada',
        'preinscripcion_apertura' => '2026-08-20T12:00:00+00:00',
        'preinscripcion_cierre_manual' => '2026-08-21T12:00:00+00:00',
        'flujo_preinscripcion' => 'gestor_preinscripciones',
    ],
    26596 => ['correo' => 'programa@flacso.edu.uy', 'inscripciones_abiertas' => '1'],
    84 => [
        'oferta_academica_id' => 26596,
        'anio' => 2028,
        'semestre' => '1S',
        'numero' => 1,
        'estado' => 'planificada',
        'preinscripcion_apertura' => '2026-08-20T12:00:00+00:00',
        'preinscripcion_cierre_manual' => '2026-08-21T12:00:00+00:00',
        'flujo_preinscripcion' => 'gestor_preinscripciones',
    ],
    26597 => ['correo' => 'seminarios@flacso.edu.uy'],
    85 => [
        'oferta_academica_id' => 26597,
        'estado' => 'planificada',
        'preinscripcion_apertura' => null,
        '_legacy_preinscripciones_abiertas' => true,
        'flujo_preinscripcion' => 'gestor_preinscripciones',
    ],
];
$GLOBALS['flacso_offer_types'] = [26595 => 'diploma', 26596 => 'diploma', 26597 => 'seminario'];

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function __($text, $domain = null) { return $text; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_email($value) { return filter_var((string) $value, FILTER_SANITIZE_EMAIL); }
function absint($value) { return abs((int) $value); }
function rest_sanitize_boolean($value) { return filter_var($value, FILTER_VALIDATE_BOOLEAN); }
function get_post($id) { return $GLOBALS['flacso_posts'][(int) $id] ?? null; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['flacso_meta'][(int) $id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['flacso_meta'][(int) $id][$key] = $value; }
function get_permalink($id) { return 'https://flacso.edu.uy/formacion/diplomas/diploma-prueba/'; }
function trailingslashit($value) { return rtrim((string) $value, '/') . '/'; }
function untrailingslashit($value) { return rtrim((string) $value, '/'); }
function apply_filters($hook, $value) { return $value; }
function get_the_ID() { return 26595; }
function get_the_title($id) { return get_post($id)->post_title ?? ''; }
function wp_date($format) { return '2026'; }
function wp_get_object_terms($id, $taxonomy) { return [(object) ['slug' => $GLOBALS['flacso_offer_types'][(int) $id] ?? 'diploma']]; }
function get_post_type($id) { return get_post($id)->post_type ?? null; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function rest_ensure_response($value) { return $value; }
function mysql_to_rfc3339($value) { return str_replace(' ', 'T', $value) . 'Z'; }
function wp_unslash($value) { return $value; }
function add_query_arg($params, $url) { return $url . '?' . http_build_query($params); }
function get_posts($args) {
    $result = [];
    foreach ($GLOBALS['flacso_posts'] as $id => $post) {
        if ($post->post_type !== ($args['post_type'] ?? 'post')) continue;
        if (isset($args['post__not_in']) && in_array($id, $args['post__not_in'], true)) continue;
        $matches = true;
        foreach (($args['meta_query'] ?? []) as $clause) {
            if (!is_array($clause) || !isset($clause['key'])) continue;
            if ((string) get_post_meta($id, $clause['key'], true) !== (string) $clause['value']) $matches = false;
        }
        if ($matches) $result[] = ($args['fields'] ?? '') === 'ids' ? $id : $post;
    }
    $limit = (int) ($args['posts_per_page'] ?? -1);
    return $limit > 0 ? array_slice($result, 0, $limit) : $result;
}

class WP_Error {
    private $code;
    private $data;
    public function __construct($code = '', $message = '', $data = null) { $this->code = $code; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}

require_once __DIR__ . '/../modules/instancias-oferta/includes/class-preinscription-flow.php';
require_once __DIR__ . '/../modules/instancias-oferta/includes/class-instancia-oferta.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-academica.php';
require_once __DIR__ . '/../modules/instancias-oferta/includes/class-preinscription-url-resolver.php';
require_once __DIR__ . '/../modules/instancias-oferta/includes/class-instancia-oferta-api.php';
require_once __DIR__ . '/../modules/instancias-oferta/includes/class-preinscription-catalog-api.php';

function flow_assert_same($expected, $actual, $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "Fallo en {$label}: esperado " . var_export($expected, true) . ', obtuve ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

flow_assert_same('legacy_editor', FLACSO_Preinscription_Flow::normalize(''), 'default legacy');
flow_assert_same(
    'https://preinscripciones.flacso.edu.uy/ofertas/26595/instancias/81/',
    FLACSO_Preinscription_URL_Resolver::resolve(81),
    'URL gestor usa ambos IDs WordPress'
);
flow_assert_same(
    'https://flacso.edu.uy/formacion/diplomas/diploma-prueba/preinscripcion/',
    FLACSO_Preinscription_URL_Resolver::resolve(82),
    'URL legacy se conserva'
);

$locked = FLACSO_Instancia_Oferta_API::validate_payload([
    'academicOfferId' => 26595,
    'name' => 'Cohorte 2027',
    'year' => 2027,
    'number' => 2,
    'status' => 'planificada',
    'preinscriptionFlow' => 'legacy_editor',
], 81);
flow_assert_same(true, $locked instanceof WP_Error, 'flujo bloqueado tras apertura');
flow_assert_same('flacso_instance_flow_locked', $locked->get_error_code(), 'codigo de bloqueo');
$nullable = FLACSO_Instancia_Oferta_API::validate_payload([
    'academicOfferId' => 26595,
    'name' => 'Cohorte sin número',
    'status' => 'planificada',
    'preinscriptionFlow' => 'gestor_preinscripciones',
]);
flow_assert_same(null, $nullable['number'], 'numero nullable');
flow_assert_same('', $nullable['startDate'], 'fecha nullable');

$catalog_item = FLACSO_Preinscription_Catalog_API::build_item(81);
flow_assert_same(2, count(array_intersect(['oferta', 'instancia'], array_keys($catalog_item))), 'sanity contrato');
flow_assert_same(26595, $catalog_item['oferta']['id'], 'catalogo usa ID oferta WordPress');
flow_assert_same(81, $catalog_item['instancia']['id'], 'catalogo usa ID instancia WordPress');
flow_assert_same(false, array_key_exists('flujo_preinscripcion', $catalog_item['instancia']), 'catalogo no filtra meta crudo');
flow_assert_same(null, FLACSO_Preinscription_Catalog_API::build_item(82), 'legacy fuera del catalogo nuevo');
flow_assert_same(null, FLACSO_Preinscription_Catalog_API::build_item(83), 'gestor cerrado fuera del catalogo');
flow_assert_same('seminario', FLACSO_Preinscription_Catalog_API::build_item(85)['oferta']['tipo'], 'catalogo trata seminario como OfertaAcademica');
flow_assert_same(false, flacso_offer_accepts_preinscriptions(26596), 'instancia explicita cerrada ignora booleano legacy');
$closed_cta = flacso_get_preinscription_cta(26596);
flow_assert_same(false, $closed_cta['is_open'], 'CTA explicito cerrado');
flow_assert_same('gestor_preinscripciones', $closed_cta['flow'], 'CTA cerrado conserva flujo');
flow_assert_same('https://preinscripciones.flacso.edu.uy/ofertas/26596/instancias/84/', $closed_cta['url'], 'CTA cerrado no vuelve al legacy');
$catalog = FLACSO_Preinscription_Catalog_API::get_catalog();
flow_assert_same(1, $catalog['schema_version'], 'catalogo schema v1');
flow_assert_same(2, count($catalog['items']), 'catalogo incluye seminario y demas tipos abiertos');

FLACSO_Instancia_Oferta::close_other_open_instances(26595, 81);
flow_assert_same('planificada', get_post_meta(82, 'estado', true), 'cerrar no altera estado academico');
flow_assert_same(true, get_post_meta(82, 'preinscripcion_cierre_manual', true) !== '', 'cierre se registra por timestamp');

fwrite(STDOUT, "OK preinscription flows contract\n");
