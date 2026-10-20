<?php
/**
 * Regresión: portada de Oferta y carta deben tomar la cohorte publicada
 * de mayor número sin modificar el resolvedor operativo de consultas.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
function absint($value) { return abs((int) $value); }
function current_time($type) { return '2026-10-20'; }
$GLOBALS['cohort_fixtures'] = [];
final class FLACSO_Academic_Repository {
    public static function to_array($entity, $id) {
        return $entity === 'ofertas' ? ['id' => $id, 'nombre' => 'Oferta de prueba'] : [];
    }
    public static function list($entity, $filters = []) {
        return $GLOBALS['cohort_fixtures'][(int) ($filters['parent_id'] ?? 0)] ?? [];
    }
}
require_once dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-catalog.php';
function latest_assert($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: " . $message . "\n"); exit(1); }
}
$old = [
    'id' => 101, 'numero' => 5, 'estado_publicacion' => 'publish',
    'estado' => 'en_curso', 'fecha_inicio' => '2026-03-01',
    'preinscripcion' => ['abierta' => true, 'url' => 'https://example.org/101'],
];
$latest = [
    'id' => 105, 'numero' => 6, 'estado_publicacion' => 'publish',
    'estado' => 'planificada', 'fecha_inicio' => '2027-03-01',
    'preinscripcion' => ['abierta' => false, 'url' => 'https://example.org/105'],
];
$draft = [
    'id' => 106, 'numero' => 7, 'estado_publicacion' => 'draft',
    'estado' => 'planificada', 'fecha_inicio' => '2028-03-01',
    'preinscripcion' => ['abierta' => false],
];
$private = [
    'id' => 107, 'numero' => 8, 'estado_publicacion' => 'private',
    'estado' => 'planificada', 'fecha_inicio' => '2029-03-01',
    'preinscripcion' => ['abierta' => false],
];
$GLOBALS['cohort_fixtures'][10] = [$old, $draft, $latest, $private];
$offer = FLACSO_Academic_Catalog::get_offer(10);
latest_assert($offer['cohorte_mas_reciente']['id'] === 105, 'página pública presenta cohorte VI, no V abierta ni futuras en borrador');
latest_assert($offer['cohorte_vigente']['id'] === 101, 'cohorte_vigente operativa conserva compatibilidad');
latest_assert($offer['cohorte_consulta']['id'] === 101, 'consultas mantienen su resolvedor independiente');
latest_assert(count($offer['cohortes']) === 4, 'catálogo conserva el listado completo');

$all_closed = $old;
$all_closed['preinscripcion']['abierta'] = false;
$all_closed['estado'] = 'finalizada';
$GLOBALS['cohort_fixtures'][11] = [$all_closed, $latest];
$offer_closed = FLACSO_Academic_Catalog::get_offer(11);
latest_assert($offer_closed['cohorte_mas_reciente']['id'] === 105, 'la cohorte más nueva aparece incluso con inscripciones cerradas');

$winner = FLACSO_Academic_Catalog::latest_published_cohort([
    ['id' => 202, 'numero' => 3, 'estado_publicacion' => 'publish', 'fecha_inicio' => '2028-01-01'],
    ['id' => 201, 'numero' => 4, 'estado_publicacion' => 'publish', 'fecha_inicio' => '2027-01-01'],
]);
latest_assert($winner['id'] === 201, 'prima numeración superior aun con fecha anterior');
$winner = FLACSO_Academic_Catalog::latest_published_cohort([
    ['id' => 301, 'numero' => 4, 'estado_publicacion' => 'publish', 'fecha_inicio' => '2027-01-01'],
    ['id' => 302, 'numero' => 4, 'estado_publicacion' => 'publish', 'fecha_inicio' => '2027-02-01'],
]);
latest_assert($winner['id'] === 302, 'desempate por fecha de inicio descendente');
$winner = FLACSO_Academic_Catalog::latest_published_cohort([
    ['id' => 401, 'numero' => 4, 'estado_publicacion' => 'publish', 'fecha_inicio' => '2027-01-01'],
    ['id' => 402, 'numero' => 4, 'estado_publicacion' => 'publish', 'fecha_inicio' => '2027-01-01'],
]);
latest_assert($winner['id'] === 402, 'desempate determinístico por ID');
latest_assert(FLACSO_Academic_Catalog::latest_published_cohort([$draft, $private]) === null, 'nunca publica cohorte borrador o privada');
latest_assert(FLACSO_Academic_Catalog::latest_published_cohort([]) === null, 'sin cohortes da null');
echo "OK latest published cohort for web, operational selection unchanged\n";
