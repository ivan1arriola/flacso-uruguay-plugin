<?php

define('ABSPATH', __DIR__ . '/../');

$GLOBALS['migration_posts'] = [];
$GLOBALS['migration_meta'] = [];
$GLOBALS['migration_terms'] = [];
$GLOBALS['migration_options'] = [];
$GLOBALS['migration_writes'] = 0;

function migration_post(int $id, string $type, string $title, string $status = 'publish'): object {
    return (object) [
        'ID' => $id,
        'post_type' => $type,
        'post_status' => $status,
        'post_title' => $title,
        'post_name' => $title === '' ? (string) $id : strtolower(str_replace(' ', '-', $title)),
        'post_content' => '<p>' . $title . '</p>',
        'post_excerpt' => '',
        'post_password' => '',
        'post_modified_gmt' => '2026-08-29 12:00:00',
    ];
}

$GLOBALS['migration_posts'] = [
    24162 => migration_post(24162, 'oferta-academica', 'Maestría en Género'),
    23902 => migration_post(23902, 'seminario', 'Derechos de infancia y ciudadanía digital: oportunidades y desafíos.'),
    27254 => migration_post(27254, 'seminario', 'Seminario Derechos de infancia y ciudadanía digital: oportunidades y desafíos.'),
    27212 => migration_post(27212, 'seminario', 'Seminario integrado: Atención integral a la salud'),
    23913 => migration_post(23913, 'seminario', 'Componente salud'),
    23918 => migration_post(23918, 'seminario', 'Componente bioética'),
    27240 => migration_post(27240, 'seminario', '', 'draft'),
    27261 => migration_post(27261, 'cohorte', 'Cohorte XII'),
];
$GLOBALS['migration_meta'] = [
    24162 => ['_oferta_seminarios_ids' => [23902, 23911]],
    23902 => [
        '_seminario_presentacion_seminario' => 'Presentación anterior',
        '_seminario_objetivo_general' => 'Objetivo común',
        '_seminario_periodo_inicio' => '2026-06-24',
        '_seminario_periodo_fin' => '2026-07-15',
        '_seminario_abierto_publico' => '1',
    ],
    27254 => [
        '_seminario_presentacion_seminario' => 'Presentación nueva',
        '_seminario_objetivo_general' => 'Objetivo común',
        '_seminario_forma_aprobacion' => 'Ensayo final',
        '_seminario_periodo_inicio' => '2026-10-19',
        '_seminario_periodo_fin' => '2026-11-01',
    ],
    27212 => [
        '_seminario_es_integrado' => '1',
        '_seminario_seminarios_componentes' => [23913, 23918],
    ],
    23913 => ['_seminario_periodo_inicio' => '2026-09-01', '_seminario_periodo_fin' => '2026-09-15'],
    23918 => ['_seminario_periodo_inicio' => '2026-09-16', '_seminario_periodo_fin' => '2026-09-30'],
    27240 => ['_seminario_abierto_publico' => '0'],
    27261 => [
        'oferta_academica_id' => 24162,
        'cohort_number' => 1,
        'cohort_status' => 'upcoming',
        'start_date' => '2027-03',
        'start_date_precision' => 'month',
        'end_date' => '',
        'is_inscriptions_open' => '1',
        'flujo_preinscripcion' => 'legacy_editor',
    ],
];
$GLOBALS['migration_terms'][24162] = 'maestria';

function __($text, $domain = null) { return $text; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
function rest_sanitize_boolean($value) { return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'si', 'on'], true); }
function remove_accents($value) { return strtr($value, ['í' => 'i', 'ó' => 'o', 'é' => 'e', 'á' => 'a', 'ú' => 'u', 'ñ' => 'n', 'Í' => 'I']); }
function get_post($id) { return $GLOBALS['migration_posts'][(int) $id] ?? null; }
function get_post_type($id) { return get_post($id)->post_type ?? null; }
function get_post_meta($id, $key = '', $single = false) {
    $meta = $GLOBALS['migration_meta'][(int) $id] ?? [];
    if ($key === '') {
        $raw = [];
        foreach ($meta as $meta_key => $value) $raw[$meta_key] = [$value];
        return $raw;
    }
    return $meta[$key] ?? ($single ? '' : []);
}
function metadata_exists($type, $id, $key) { return array_key_exists($key, $GLOBALS['migration_meta'][(int) $id] ?? []); }
function update_post_meta($id, $key, $value) {
    $GLOBALS['migration_writes']++;
    $GLOBALS['migration_meta'][(int) $id][$key] = $value;
    return true;
}
function get_post_thumbnail_id($id) { return 0; }
function get_object_taxonomies($post_type) { return ['tipo-oferta-academica']; }
function wp_get_object_terms($id, $taxonomy, $args = []) {
    $slug = $GLOBALS['migration_terms'][(int) $id] ?? '';
    if (($args['fields'] ?? '') === 'ids') return $slug === '' ? [] : [crc32($slug)];
    return $slug === '' ? [] : [(object) ['slug' => $slug, 'term_id' => crc32($slug)]];
}
function wp_set_object_terms($id, $terms, $taxonomy, $append = false) {
    $GLOBALS['migration_writes']++;
    $GLOBALS['migration_terms'][(int) $id] = is_array($terms) ? (string) reset($terms) : (string) $terms;
    return [1];
}
function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : (string) $value; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_update_post($data, $wp_error = false) {
    $id = (int) ($data['ID'] ?? 0);
    if (!isset($GLOBALS['migration_posts'][$id])) return new WP_Error('missing');
    foreach ($data as $key => $value) if ($key !== 'ID') $GLOBALS['migration_posts'][$id]->{$key} = $value;
    $GLOBALS['migration_writes']++;
    return $id;
}
function wp_insert_post($data, $wp_error = false) {
    $id = max(array_keys($GLOBALS['migration_posts'])) + 1;
    $GLOBALS['migration_posts'][$id] = migration_post($id, (string) $data['post_type'], (string) $data['post_title'], (string) $data['post_status']);
    $GLOBALS['migration_writes']++;
    return $id;
}
function get_option($key, $default = false) { return $GLOBALS['migration_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['migration_writes']++; $GLOBALS['migration_options'][$key] = $value; return true; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function get_posts($args) {
    $items = [];
    foreach ($GLOBALS['migration_posts'] as $id => $post) {
        $type = $args['post_type'] ?? 'post';
        if ($type !== 'any' && (is_array($type) ? !in_array($post->post_type, $type, true) : $post->post_type !== $type)) continue;
        $status = $args['post_status'] ?? 'publish';
        if ($status !== 'any' && (is_array($status) ? !in_array($post->post_status, $status, true) : $post->post_status !== $status)) continue;
        $matches = true;
        foreach (($args['meta_query'] ?? []) as $clause) {
            if (!is_array($clause) || empty($clause['key'])) continue;
            $exists = metadata_exists('post', $id, $clause['key']);
            if (($clause['compare'] ?? '') === 'EXISTS') $matches = $matches && $exists;
            elseif ((string) get_post_meta($id, $clause['key'], true) !== (string) ($clause['value'] ?? '')) $matches = false;
        }
        if ($matches) $items[] = ($args['fields'] ?? '') === 'ids' ? $id : $post;
    }
    usort($items, static function ($a, $b): int {
        $a_id = is_object($a) ? $a->ID : $a;
        $b_id = is_object($b) ? $b->ID : $b;
        return $a_id <=> $b_id;
    });
    return $items;
}

class WP_Error {
    private $code;
    public function __construct($code = '') { $this->code = $code; }
}
class FakeMigrationDB {
    public $queries = [];
    public function query($sql) { $this->queries[] = $sql; return true; }
}
$wpdb = new FakeMigrationDB();

final class FLACSO_Cohorte_API {
    public const POST_TYPE = 'cohorte';
    public const META_OFFER_ID = 'oferta_academica_id';
    public const META_START_DATE = 'start_date';
    public const META_START_PRECISION = 'start_date_precision';
    public const META_END_DATE = 'end_date';
    public const META_STATUS = 'cohort_status';
    public const META_OPEN = 'is_inscriptions_open';
    public const META_PREINSCRIPTION_FLOW = 'flujo_preinscripcion';
    public const META_PREINSCRIPTION_START = 'preinscription_start_date';
    public const META_PREINSCRIPTION_END = 'preinscription_end_date';
    public static function sanitize_boolean($value): bool { return rest_sanitize_boolean($value); }
}

require_once __DIR__ . '/../modules/instancias-oferta/includes/class-preinscription-flow.php';
require_once __DIR__ . '/../modules/instancias-oferta/includes/class-instancia-oferta.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-oferta-academica.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-relacion-oferta-academica.php';
require_once __DIR__ . '/../modules/instancias-oferta/includes/class-academic-model-migrator.php';

function migration_assert_same($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "Fallo {$label}: esperado " . var_export($expected, true) . ', obtuve ' . var_export($actual, true) . "\n");
        exit(1);
    }
}
function migration_instances_for_origin(int $legacy_id): array {
    return array_values(array_filter($GLOBALS['migration_posts'], static function ($post) use ($legacy_id): bool {
        return $post->post_type === 'instancia-oferta'
            && (int) get_post_meta($post->ID, 'origen_legacy_id', true) === $legacy_id;
    }));
}

function migration_relation_fixture(array $destinations, array $canonical_map): array {
    $relations = [];
    foreach ($destinations as $order => $destination) {
        $relations[] = [
            'oferta_origen' => 999,
            'oferta_destino' => $destination,
            'tipo_relacion' => FLACSO_Relacion_Oferta_Academica::INTEGRA,
            'orden' => $order,
        ];
    }
    return FLACSO_Academic_Model_Migrator::canonicalize_relationships($relations, $canonical_map);
}

$canonical_map = [
    24299 => 24299,
    27242 => 24299,
    24432 => 24432,
    27245 => 24432,
    25623 => 25623,
    27256 => 25623,
    23904 => 23904,
    27258 => 23904,
    26285 => 26285,
    23902 => 23902,
    27254 => 23902,
];

$relations_24169 = migration_relation_fixture([24299, 24432, 27242, 27245], $canonical_map);
migration_assert_same([24299, 24432], array_column($relations_24169['final'], 'oferta_destino'), '24169 deduplica ediciones canonicalizadas');
migration_assert_same([0, 1], array_column($relations_24169['final'], 'orden'), '24169 conserva primera aparicion');
migration_assert_same(2, count($relations_24169['absorbed']), '24169 reporta relaciones absorbidas');

$relations_24170 = migration_relation_fixture([25623, 23904, 26285, 27256, 27258], $canonical_map);
migration_assert_same([25623, 23904, 26285], array_column($relations_24170['final'], 'oferta_destino'), '24170 deduplica ediciones canonicalizadas');
migration_assert_same(2, count($relations_24170['absorbed']), '24170 reporta relaciones absorbidas');

$relations_24173 = migration_relation_fixture([23902, 27254], $canonical_map);
migration_assert_same([23902], array_column($relations_24173['final'], 'oferta_destino'), '24173 deduplica ediciones canonicalizadas');
migration_assert_same(1, count($relations_24173['absorbed']), '24173 reporta relacion absorbida');

$normalized_relations = FLACSO_Relacion_Oferta_Academica::normalize([
    ['oferta_destino' => 24299, 'tipo_relacion' => 'integra', 'orden' => 0],
    ['oferta_destino' => 24432, 'tipo_relacion' => 'integra', 'orden' => 1],
    ['oferta_destino' => 24299, 'tipo_relacion' => 'integra', 'orden' => 2],
]);
migration_assert_same([0, 1], array_column($normalized_relations, 'orden'), 'persistencia conserva el orden de la primera relacion');

$before = serialize([$GLOBALS['migration_posts'], $GLOBALS['migration_meta'], $GLOBALS['migration_terms'], $GLOBALS['migration_options']]);
$dry = FLACSO_Academic_Model_Migrator::run(true);
$after = serialize([$GLOBALS['migration_posts'], $GLOBALS['migration_meta'], $GLOBALS['migration_terms'], $GLOBALS['migration_options']]);
migration_assert_same($before, $after, 'dry-run no modifica DB');
migration_assert_same(0, $GLOBALS['migration_writes'], 'dry-run no llama APIs de escritura');
migration_assert_same([27254], $dry['duplicados_absorbidos'], 'duplicado explicito absorbido');
migration_assert_same(27240, $dry['seminarios_invalidos'][0]['id'], 'invalido omitido');
migration_assert_same([], $dry['referencias_rotas'], 'no quedan referencias rotas desconocidas');
migration_assert_same(23911, $dry['referencias_huerfanas_conocidas'][0]['missing_id'], 'referencia huerfana conocida auditada');
migration_assert_same('OMITIR', $dry['referencias_huerfanas_conocidas'][0]['action'], 'referencia huerfana conocida se omite');
migration_assert_same(27212, $dry['registros_sin_fechas'][0]['id'], 'integrado sin instancia');
migration_assert_same([], $dry['conflictos_academicos'], 'no quedan conflictos academicos sin resolver');
migration_assert_same(['_seminario_presentacion_seminario'], $dry['conflictos_academicos_resueltos'][0]['fields'], 'diferencia academica resuelta por canonico');
migration_assert_same(23902, $dry['conflictos_academicos_resueltos'][0]['academic_source'], 'canonico es fuente academica');
migration_assert_same(5, $dry['expected_final_counts']['oferta_academica'], 'conteo final ofertas fixture');
migration_assert_same(5, $dry['expected_final_counts']['instancia_oferta'], 'conteo final instancias fixture');
migration_assert_same(4, $dry['resumen_relaciones']['legacy_leidas'], 'dry-run cuenta relaciones legacy incluidas las rotas');
migration_assert_same(3, $dry['resumen_relaciones']['finales_deduplicadas'], 'dry-run cuenta relaciones finales');
migration_assert_same(0, $dry['resumen_relaciones']['absorbidas_por_canonicalizacion'], 'fixture base sin relaciones repetidas');
migration_assert_same(1, $dry['resumen_relaciones']['huerfanas_conocidas'], 'dry-run cuenta referencia huerfana conocida');

$applied = FLACSO_Academic_Model_Migrator::run(false);
migration_assert_same('instancia-oferta', get_post_type(27261), 'cohorte conserva ID');
migration_assert_same(24162, (int) get_post_meta(27261, 'oferta_academica_id', true), 'cohorte conserva oferta');
migration_assert_same('Cohorte XII', get_post(27261)->post_title, 'cohorte conserva nombre visible');
migration_assert_same('2027-03', get_post_meta(27261, 'fecha_inicio', true), 'cohorte conserva fecha');
migration_assert_same('', get_post_meta(27261, 'numero', true), 'cohorte no inventa numero');
migration_assert_same('oferta-academica', get_post_type(23902), 'ID canonico preservado como oferta');
migration_assert_same('instancia-oferta', get_post_type(27254), 'absorbido se convierte en instancia');
migration_assert_same('ABSORBIDO_COMO_EDICION', get_post_meta(27254, FLACSO_Academic_Model_Migrator::RECORD_META_KEY, true)['action'], 'accion absorbido auditada');
migration_assert_same(1, count(migration_instances_for_origin(23902)), 'seminario canonico crea una instancia');
migration_assert_same(1, count(migration_instances_for_origin(27254)), 'duplicado crea segunda instancia');
migration_assert_same(0, count(migration_instances_for_origin(27212)), 'integrado sin temporalidad no crea instancia');
migration_assert_same('Presentación anterior', get_post_meta(23902, '_seminario_presentacion_seminario', true), 'contenido academico canonico preservado');
migration_assert_same('Ensayo final', get_post_meta(23902, '_seminario_forma_aprobacion', true), 'campo academico vacio completado desde edicion absorbida');
migration_assert_same(23902, get_post_meta(23902, FLACSO_Academic_Model_Migrator::RECORD_META_KEY, true)['fuente_academica'], 'registro audita fuente academica canonica');
$components = FLACSO_Relacion_Oferta_Academica::get(27212, FLACSO_Relacion_Oferta_Academica::COMPUESTO_POR);
migration_assert_same([23913, 23918], array_column($components, 'oferta_destino'), 'seminario integrado conserva componentes');
$integrates = FLACSO_Relacion_Oferta_Academica::get(24162, FLACSO_Relacion_Oferta_Academica::INTEGRA);
migration_assert_same([23902], array_column($integrates, 'oferta_destino'), 'referencia rota omitida sin abortar');
migration_assert_same(true, metadata_exists('post', 23902, FLACSO_Academic_Model_Migrator::BACKUP_META_KEY), 'backup canonico');
migration_assert_same(true, metadata_exists('post', 27254, FLACSO_Academic_Model_Migrator::BACKUP_META_KEY), 'backup absorbido');
migration_assert_same(true, metadata_exists('post', 24162, FLACSO_Academic_Model_Migrator::BACKUP_META_KEY), 'backup antes de relacion');

$offer_count = count(get_posts(['post_type' => 'oferta-academica', 'post_status' => ['publish', 'draft', 'private', 'pending', 'future']]));
$instance_count = count(get_posts(['post_type' => 'instancia-oferta', 'post_status' => ['publish', 'draft', 'private', 'pending', 'future']]));
FLACSO_Academic_Model_Migrator::run(false);
migration_assert_same($offer_count, count(get_posts(['post_type' => 'oferta-academica', 'post_status' => ['publish', 'draft', 'private', 'pending', 'future']])), 'segunda ejecucion no duplica ofertas');
migration_assert_same($instance_count, count(get_posts(['post_type' => 'instancia-oferta', 'post_status' => ['publish', 'draft', 'private', 'pending', 'future']])), 'segunda ejecucion no duplica instancias');
migration_assert_same(true, count(get_option(FLACSO_Academic_Model_Migrator::MAP_OPTION, [])) >= count($applied['migration_map']), 'mapa auditable no se pierde');

fwrite(STDOUT, "OK academic model migration\n");
