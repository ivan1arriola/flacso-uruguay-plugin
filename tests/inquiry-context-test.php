<?php
/**
 * Test unitario para FLACSO_Inquiry_Context_Service.
 *
 * @package FLACSO_Uruguay
 */

$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

if (!function_exists('absint')) {
    function absint($val) {
        return abs((int) $val);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($str) {
        $str = strtolower(trim((string) $str));
        $str = preg_replace('/[^a-z0-9_\-]+/', '-', $str);
        return trim($str, '-');
    }
}

if (!class_exists('FLACSO_Oferta_Academica') && file_exists($root . '/modules/oferta-academica/includes/class-oferta-academica.php')) {
    require_once $root . '/modules/oferta-academica/includes/class-oferta-academica.php';
}

$GLOBALS['mock_catalog_offers'] = [];
$GLOBALS['mock_catalog_inquiry_cohorts'] = [];
$GLOBALS['mock_catalog_throw'] = false;
$GLOBALS['mock_catalog_inquiry_throw'] = false;
$GLOBALS['mock_get_post_called_with'] = null;

if (!function_exists('get_post')) {
    function get_post($id = 0) {
        $GLOBALS['mock_get_post_called_with'] = $id;
        if ($id <= 0) {
            $obj = new stdClass();
            $obj->post_title = 'Post Global Inesperado';
            return $obj;
        }
        return null;
    }
}

if (!class_exists('FLACSO_Academic_Catalog')) {
    class FLACSO_Academic_Catalog {
        public static function get_offer(int $offer_id): array {
            if (!empty($GLOBALS['mock_catalog_throw'])) {
                throw new \RuntimeException('Error simulado en catálogo');
            }
            return $GLOBALS['mock_catalog_offers'][$offer_id] ?? [];
        }

        public static function get_inquiry_cohort(int $offer_id): ?array {
            if (!empty($GLOBALS['mock_catalog_inquiry_throw'])) {
                throw new \RuntimeException('Error simulado en get_inquiry_cohort');
            }
            return $GLOBALS['mock_catalog_inquiry_cohorts'][$offer_id] ?? null;
        }
    }
}

function test_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$service_file = $root . '/modules/consultas/services/class-flacso-inquiry-context-service.php';
if (file_exists($service_file)) {
    require_once $service_file;
}

if (!class_exists('FLACSO_Inquiry_Context_Service')) {
    fwrite(STDERR, "FAIL: Class FLACSO_Inquiry_Context_Service does not exist\n");
    exit(1);
}

// -------------------------------------------------------------
// Test 1: Oferta con cohorte abierta
// -------------------------------------------------------------
$GLOBALS['mock_catalog_offers'][1] = [
    'id' => 1,
    'nombre' => 'Maestría en Educación y Tecnología',
    'tipo' => 'maestria',
    'abreviacion' => 'MET-2026',
    'correo' => 'met@flacso.edu.uy',
    'cohorte_consulta' => [
        'id' => 101,
        'numero' => 3,
        'nombre' => '3ª Cohorte (2026)',
        'fecha_inicio' => '2026-10-15',
        'precision_fecha_inicio' => 'dia',
        'modalidad' => 'virtual',
        'preinscripcion' => [
            'abierta' => true,
            'desde' => '2026-08-01',
            'hasta' => '2026-10-01',
            'url' => 'https://pre.flacso.edu.uy/met/3',
        ],
    ],
];

$ctx1 = FLACSO_Inquiry_Context_Service::resolve(1);

test_assert($ctx1['offerWpId'] === 1, 'Test 1: offerWpId debe ser 1');
test_assert($ctx1['offerName'] === 'Maestría en Educación y Tecnología', 'Test 1: offerName correcto');
test_assert($ctx1['offerType'] === 'maestria', 'Test 1: offerType correcto');
test_assert($ctx1['offerAbbreviation'] === 'met-2026', 'Test 1: offerAbbreviation normalizado canónico');
test_assert($ctx1['cohortWpId'] === 101, 'Test 1: cohortWpId debe ser 101');
test_assert($ctx1['cohortNumber'] === 3, 'Test 1: cohortNumber debe ser 3');
test_assert($ctx1['cohortName'] === '3ª Cohorte (2026)', 'Test 1: cohortName correcto');
test_assert($ctx1['registrationOpenAt'] === '2026-08-01', 'Test 1: registrationOpenAt capturado');
test_assert($ctx1['registrationCloseAt'] === '2026-10-01', 'Test 1: registrationCloseAt capturado');
test_assert($ctx1['offerStatus'] === 'abierta', 'Test 1: offerStatus debe ser abierta');
test_assert($ctx1['preinscripcionUrl'] === 'https://pre.flacso.edu.uy/met/3', 'Test 1: preinscripcionUrl capturada');
test_assert($ctx1['replyToEmail'] === 'met@flacso.edu.uy', 'Test 1: replyToEmail capturado');
test_assert($ctx1['startValue'] === '2026-10-15', 'Test 1: startValue capturado');
test_assert($ctx1['startPrecision'] === 'dia', 'Test 1: startPrecision capturado');
test_assert($ctx1['modalityLabel'] === 'virtual', 'Test 1: modalityLabel capturado');

// -------------------------------------------------------------
// Test 2: Oferta con cohorte planificada (preinscripción no abierta)
// -------------------------------------------------------------
$GLOBALS['mock_catalog_offers'][2] = [
    'id' => 2,
    'nombre' => 'Diploma en Género',
    'tipo' => 'diploma',
    'abreviacion' => 'diploma-genero',
    'correo' => 'genero@flacso.edu.uy',
    'cohorte_consulta' => [
        'id' => 102,
        'numero' => 5,
        'nombre' => '5ª Cohorte (2027)',
        'fecha_inicio' => '2027-03-01',
        'precision_fecha_inicio' => 'mes',
        'modalidad' => 'presencial',
        'preinscripcion' => [
            'abierta' => false,
            'desde' => '2027-01-15',
            'hasta' => '2027-02-28',
            'url' => 'https://pre.flacso.edu.uy/genero/5',
        ],
    ],
];

$ctx2 = FLACSO_Inquiry_Context_Service::resolve(2);

test_assert($ctx2['offerStatus'] === 'cerrada', 'Test 2: offerStatus debe ser cerrada');
test_assert($ctx2['cohortWpId'] === 102, 'Test 2: cohortWpId debe ser 102');
test_assert($ctx2['cohortNumber'] === 5, 'Test 2: cohortNumber debe ser 5');
test_assert($ctx2['cohortName'] === '5ª Cohorte (2027)', 'Test 2: cohortName capturado');
test_assert($ctx2['registrationOpenAt'] === '2027-01-15', 'Test 2: registrationOpenAt capturado');
test_assert($ctx2['registrationCloseAt'] === '2027-02-28', 'Test 2: registrationCloseAt capturado');
test_assert($ctx2['preinscripcionUrl'] === 'https://pre.flacso.edu.uy/genero/5', 'Test 2: preinscripcionUrl capturada');

// -------------------------------------------------------------
// Test 3: Oferta sin cohorte
// -------------------------------------------------------------
$GLOBALS['mock_catalog_offers'][3] = [
    'id' => 3,
    'nombre' => 'Especialización sin cohorte',
    'tipo' => 'especializacion',
    'abreviacion' => 'esp-vacia',
    'cohorte_consulta' => null,
];

$ctx3 = FLACSO_Inquiry_Context_Service::resolve(3);

test_assert($ctx3['offerStatus'] === 'sin_cohorte', 'Test 3: offerStatus debe ser sin_cohorte');
test_assert($ctx3['cohortWpId'] === null, 'Test 3: cohortWpId debe ser null');
test_assert($ctx3['cohortNumber'] === null, 'Test 3: cohortNumber debe ser null');
test_assert($ctx3['cohortName'] === null, 'Test 3: cohortName debe ser null');
test_assert($ctx3['registrationOpenAt'] === null, 'Test 3: registrationOpenAt debe ser null');
test_assert($ctx3['registrationCloseAt'] === null, 'Test 3: registrationCloseAt debe ser null');
test_assert($ctx3['preinscripcionUrl'] === null, 'Test 3: preinscripcionUrl debe ser null');

// -------------------------------------------------------------
// Test 4: Oferta sin abreviación -> offerAbbreviation = null, sin arrojar excepción
// -------------------------------------------------------------
$GLOBALS['mock_catalog_offers'][4] = [
    'id' => 4,
    'nombre' => 'Curso Sin Abreviación',
    'tipo' => 'curso',
    'abreviacion' => '',
    'cohorte_consulta' => null,
];

$exception_thrown = false;
$ctx4 = null;
try {
    $ctx4 = FLACSO_Inquiry_Context_Service::resolve(4);
} catch (\Throwable $e) {
    $exception_thrown = true;
}

test_assert(!$exception_thrown, 'Test 4: No debe arrojar excepción si falta abreviación');
test_assert(is_array($ctx4), 'Test 4: Retorna array');
test_assert($ctx4['offerAbbreviation'] === null, 'Test 4: offerAbbreviation debe ser null');
test_assert($ctx4['offerStatus'] === 'sin_cohorte', 'Test 4: offerStatus debe ser sin_cohorte');

// -------------------------------------------------------------
// Test 5: Fallback a get_inquiry_cohort cuando no está en el array directo
// -------------------------------------------------------------
$GLOBALS['mock_catalog_offers'][5] = [
    'id' => 5,
    'nombre' => 'Maestría Fallback Cohorte',
    'tipo' => 'maestria',
    'abreviacion' => 'mfc',
];
$GLOBALS['mock_catalog_inquiry_cohorts'][5] = [
    'id' => 505,
    'numero' => 1,
    'nombre' => '1ª Cohorte',
    'preinscripcion' => [
        'abierta' => true,
    ],
];

$ctx5 = FLACSO_Inquiry_Context_Service::resolve(5);
test_assert($ctx5['cohortWpId'] === 505, 'Test 5: Resolvió cohorte desde get_inquiry_cohort');
test_assert($ctx5['offerStatus'] === 'abierta', 'Test 5: offerStatus es abierta');

// -------------------------------------------------------------
// Test 6: inquiry_data sobrescribe valores
// -------------------------------------------------------------
$ctx6 = FLACSO_Inquiry_Context_Service::resolve(1, [
    'offerName' => 'Nombre Personalizado',
    'offerType' => 'tipo_custom',
    'offerAbbreviation' => 'CUSTOM-ABBR',
]);
test_assert($ctx6['offerName'] === 'Nombre Personalizado', 'Test 6: offerName sobrescrito');
test_assert($ctx6['offerType'] === 'tipo_custom', 'Test 6: offerType sobrescrito');
test_assert($ctx6['offerAbbreviation'] === 'custom-abbr', 'Test 6: offerAbbreviation normalizado desde inquiry_data');

// -------------------------------------------------------------
// Test 7: Manejo resiliente si el catálogo lanza excepción
// -------------------------------------------------------------
$GLOBALS['mock_catalog_throw'] = true;
$ctx7 = FLACSO_Inquiry_Context_Service::resolve(99, ['offerName' => 'Oferta Resiliente']);
$GLOBALS['mock_catalog_throw'] = false;
test_assert($ctx7['offerName'] === 'Oferta Resiliente', 'Test 7: Resiliente ante error en catálogo');
test_assert($ctx7['offerStatus'] === 'sin_cohorte', 'Test 7: Status default sin_cohorte');

// -------------------------------------------------------------
// Test 8: Resiliencia si get_inquiry_cohort arroja excepción
// -------------------------------------------------------------
$GLOBALS['mock_catalog_offers'][8] = [
    'id' => 8,
    'nombre' => 'Oferta Excepción en Cohorte',
    'cohorte_consulta' => null,
];
$GLOBALS['mock_catalog_inquiry_throw'] = true;
$ctx8 = FLACSO_Inquiry_Context_Service::resolve(8);
$GLOBALS['mock_catalog_inquiry_throw'] = false;
test_assert($ctx8['cohortWpId'] === null, 'Test 8: cohortWpId debe ser null tras excepción');
test_assert($ctx8['offerStatus'] === 'sin_cohorte', 'Test 8: Status default sin_cohorte tras excepción');

// -------------------------------------------------------------
// Test 9: Guarda de get_post con offer_id <= 0
// -------------------------------------------------------------
$GLOBALS['mock_get_post_called_with'] = null;
$ctx9 = FLACSO_Inquiry_Context_Service::resolve(0, ['offerName' => '']);
test_assert($GLOBALS['mock_get_post_called_with'] === null, 'Test 9: get_post NO debe ser llamado si offer_id <= 0');
test_assert($ctx9['offerName'] === '', 'Test 9: offerName no debe ser contaminado por get_post(0)');

echo "OK inquiry-context-test\n";
