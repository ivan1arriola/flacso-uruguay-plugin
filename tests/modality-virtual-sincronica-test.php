<?php
/**
 * La modalidad «Virtual con encuentros sincrónicos» (`virtual-sincronica`) debe estar en todas las listas
 * cerradas: si falta en una, el valor se descarta en silencio y queda vacío.
 */
$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-payload-builder.php';

function modality_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$cohorte = file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');
modality_assert(strpos($cohorte, "['virtual', 'virtual-sincronica', 'presencial', 'semipresencial', 'hibrida']") !== false, 'sanitize_modality acepta el valor');
modality_assert(strpos($cohorte, 'value="virtual-sincronica"') !== false, 'el selector de cohorte lo ofrece');
modality_assert(strpos($cohorte, 'Virtual con encuentros sincrónicos') !== false, 'etiqueta visible en el selector');
modality_assert(strpos(file_get_contents($root . '/modules/academico/logica/dominio/valores/Modalidad.php'), "'virtual-sincronica'") !== false, 'el valor de dominio lo permite');
modality_assert(strpos(file_get_contents($root . '/modules/consultas/services/class-flacso-inquiry-context-service.php'), "'virtual-sincronica'") !== false, 'el servicio de contexto lo permite');

// Orden de detección en el normalizador: «virtual asincrónica» NO es sincrónica.
$normalizador = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-meta-normalizer.php');
modality_assert(strpos($normalizador, "!str_contains(\$value, 'asincron')") !== false, 'el normalizador no confunde asincrónica');
modality_assert(strpos($normalizador, "return 'virtual-sincronica';") < strpos($normalizador, "return 'virtual';"), 'sincrónica se evalúa antes que virtual a secas');

// De punta a punta por el snapshot que viaja a Mautic.
$form = ['event_id' => 'q-9', 'nombre' => 'Ana', 'apellido' => 'Pérez', 'correo' => 'ana@example.org', 'pais' => 'Uruguay',
    'profesion' => 'Docente', 'nivel_academico' => 'Posgrado', 'consulta' => 'x', 'programUrl' => 'https://flacso.edu.uy/davia'];
$context = ['offerWpId' => 10, 'offerName' => 'Diploma DAVIA', 'offerAbbreviation' => 'DAVIA', 'offerType' => 'diploma',
    'cohortWpId' => 100, 'cohortNumber' => 10, 'cohortName' => 'Cohorte 10', 'offerStatus' => 'abierta',
    'startDate' => '2027-04-08', 'startDatePrecision' => 'day', 'modality' => 'virtual-sincronica',
    'preinscripcionUrl' => 'https://preinscripciones.flacso.edu.uy/davia-c10'];
$snapshot = FLACSO_Inquiry_Snapshot::from_offer($form, $context, 'q-9');
modality_assert($snapshot['academic']['modality'] === 'virtual-sincronica', 'el snapshot conserva el valor');
$payload = FLACSO_Mautic_Payload_Builder::build($snapshot);
modality_assert($payload['tokens']['{modalidad}'] === 'Virtual con encuentros sincrónicos', 'el token del correo muestra la etiqueta completa');

// Escrito con guion bajo o con otra capitalización también se reconoce.
$context['modality'] = 'Virtual_Sincronica';
modality_assert(FLACSO_Inquiry_Snapshot::from_offer($form, $context, 'q-9')['academic']['modality'] === 'virtual-sincronica', 'tolera mayúsculas y guion bajo');
echo "OK modality-virtual-sincronica-test\n";
