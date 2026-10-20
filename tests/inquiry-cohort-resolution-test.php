<?php
// tests/inquiry-cohort-resolution-test.php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('absint')) {
    function absint($val) {
        return abs((int) $val);
    }
}

$GLOBALS['mock_cohorts'] = [];

class FLACSO_Academic_Repository {
    public static function to_array($type, $id) {
        return ['id' => $id, 'nombre' => 'Oferta Test'];
    }
    public static function list($type, $filters = []) {
        $parent = $filters['parent_id'] ?? 0;
        return $GLOBALS['mock_cohorts'][$parent] ?? [];
    }
}

require_once $root . '/modules/oferta-academica/includes/class-academic-catalog.php';

function test_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// Escenario 1: Cohorte con preinscripción abierta
$GLOBALS['mock_cohorts'][1] = [
    [
        'id' => 10,
        'numero' => 10,
        'estado_publicacion' => 'publish',
        'nombre' => 'Cohorte X',
        'fecha_inicio' => '2026-10-01',
        'preinscripcion' => ['abierta' => true, 'url' => 'https://pre.test/10'],
        'estado' => 'planificada',
    ],
    [
        'id' => 9,
        'numero' => 9,
        'estado_publicacion' => 'publish',
        'nombre' => 'Cohorte IX',
        'fecha_inicio' => '2025-10-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'finalizada',
    ],
];
$c1 = FLACSO_Academic_Catalog::get_inquiry_cohort(1);
test_assert($c1 !== null && $c1['id'] === 10, 'Debe elegir cohorte 10 abierta');

// Escenario 2: Sin cohorte abierta, pero cohorte futura planificada
$GLOBALS['mock_cohorts'][2] = [
    [
        'id' => 11,
        'numero' => 11,
        'estado_publicacion' => 'publish',
        'nombre' => 'Cohorte XI',
        'fecha_inicio' => '2027-03-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'planificada',
    ],
    [
        'id' => 10,
        'numero' => 10,
        'estado_publicacion' => 'publish',
        'nombre' => 'Cohorte X',
        'fecha_inicio' => '2026-03-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'finalizada',
    ],
];
$c2 = FLACSO_Academic_Catalog::get_inquiry_cohort(2);
test_assert($c2 !== null && $c2['id'] === 11, 'Debe elegir cohorte futura 11');

// Escenario 3: Sin cohorte abierta ni futura
$GLOBALS['mock_cohorts'][3] = [
    [
        'id' => 10,
        'numero' => 10,
        'estado_publicacion' => 'publish',
        'nombre' => 'Cohorte X',
        'fecha_inicio' => '2025-03-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'finalizada',
    ],
];
$c3 = FLACSO_Academic_Catalog::get_inquiry_cohort(3);
test_assert($c3 !== null && $c3['id'] === 10, 'La consulta mantiene la cohorte más nueva incluso finalizada');

// Escenario 4: get_offer incluye cohorte_consulta
$offer = FLACSO_Academic_Catalog::get_offer(1);
test_assert(isset($offer['cohorte_consulta']) && $offer['cohorte_consulta']['id'] === 10, 'get_offer incluye cohorte_consulta');

// Escenario 5: Múltiples cohortes abiertas (desempate por fecha más próxima y luego mayor número)
$GLOBALS['mock_cohorts'][5] = [
    [
        'id' => 21,
        'numero' => 2,
        'estado_publicacion' => 'publish',
        'fecha_inicio' => '2027-01-01',
        'preinscripcion' => ['abierta' => true],
        'estado' => 'planificada',
    ],
    [
        'id' => 22,
        'numero' => 3,
        'estado_publicacion' => 'publish',
        'fecha_inicio' => '2027-01-01',
        'preinscripcion' => ['abierta' => true],
        'estado' => 'planificada',
    ],
];
$c5 = FLACSO_Academic_Catalog::get_inquiry_cohort(5);
test_assert($c5 !== null && $c5['id'] === 22, 'En empate de fecha abierta, desempata por mayor número');

// Escenario 6: Planificadas futuras con fecha vacía vs fecha futura
$GLOBALS['mock_cohorts'][6] = [
    [
        'id' => 31,
        'numero' => 1,
        'estado_publicacion' => 'publish',
        'fecha_inicio' => '',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'planificada',
    ],
    [
        'id' => 32,
        'numero' => 2,
        'estado_publicacion' => 'publish',
        'fecha_inicio' => '2028-06-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'planificada',
    ],
];
$c6 = FLACSO_Academic_Catalog::get_inquiry_cohort(6);
test_assert($c6 !== null && $c6['id'] === 32, 'Fecha futura con fecha definida prevalece sobre fecha vacía');

// Escenario 7: Oferta sin cohortes registradas
$c7 = FLACSO_Academic_Catalog::get_inquiry_cohort(999);
test_assert($c7 === null, 'Oferta sin cohortes retorna null');

// Escenario 8: Cohorte planificada con fecha en el pasado no debe ser elegida
$GLOBALS['mock_cohorts'][8] = [
    [
        'id' => 41,
        'numero' => 1,
        'estado_publicacion' => 'publish',
        'fecha_inicio' => '2020-01-01',
        'preinscripcion' => ['abierta' => false],
        'estado' => 'planificada',
    ],
];
$c8 = FLACSO_Academic_Catalog::get_inquiry_cohort(8);
test_assert($c8 !== null && $c8['id'] === 41, 'La cohorte publicada más nueva mantiene su identidad aunque tenga fecha pasada');

// Escenario 9: Pasar cohortes pre-cargadas explícitamente sin consultar el repositorio
$custom_cohorts = [
    [
        'id' => 99,
        'numero' => 1,
        'estado_publicacion' => 'publish',
        'fecha_inicio' => '2028-01-01',
        'preinscripcion' => ['abierta' => true],
        'estado' => 'planificada',
    ],
];
$c9 = FLACSO_Academic_Catalog::get_inquiry_cohort(999, $custom_cohorts);
test_assert($c9 !== null && $c9['id'] === 99, 'Debe resolver desde array de cohortes provisto explícitamente');

// Escenario 10: la cohorte V permanece abierta, pero la VI publicada es
// la referencia para consultas aunque tenga inscripciones cerradas.
$GLOBALS['mock_cohorts'][10] = [
    [
        'id' => 51, 'numero' => 5, 'estado_publicacion' => 'publish',
        'fecha_inicio' => '2026-02-01', 'estado' => 'en_curso',
        'preinscripcion' => ['abierta' => true],
    ],
    [
        'id' => 52, 'numero' => 6, 'estado_publicacion' => 'publish',
        'fecha_inicio' => '2027-02-01', 'estado' => 'planificada',
        'preinscripcion' => ['abierta' => false],
    ],
    [
        'id' => 53, 'numero' => 7, 'estado_publicacion' => 'draft',
        'fecha_inicio' => '2028-02-01', 'estado' => 'planificada',
        'preinscripcion' => ['abierta' => true],
    ],
];
$c10 = FLACSO_Academic_Catalog::get_inquiry_cohort(10);
test_assert($c10 !== null && $c10['id'] === 52, 'Las consultas muestran VI, no V abierta ni VII en borrador');
$offer10 = FLACSO_Academic_Catalog::get_offer(10);
test_assert($offer10['cohorte_mas_reciente']['id'] === $offer10['cohorte_consulta']['id'], 'La web y consultas comparten ID de cohorte');
test_assert($offer10['cohorte_vigente']['id'] === $offer10['cohorte_consulta']['id'], 'La cohorte vigente coincide en los DTO');
test_assert(empty($offer10['cohorte_consulta']['preinscripcion']['abierta']), 'VI conserva su cierre de preinscripción');

echo "OK inquiry-cohort-resolution-test\n";
