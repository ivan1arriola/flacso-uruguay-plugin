<?php
$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-payload-builder.php';

function snapshot_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$form = [
    'event_id' => 'q-1',
    'nombre' => 'Ana',
    'apellido' => 'Pérez',
    'correo' => 'ana@example.org',
    'pais' => 'Uruguay',
    'profesion' => 'Docente',
    'nivel_academico' => 'Posgrado',
    'consulta' => 'Este texto no debe salir de WordPress',
    'programUrl' => 'https://flacso.edu.uy/davia',
];

$context = [
    'offerWpId' => 10,
    'offerName' => 'Diploma DAVIA',
    'offerAbbreviation' => 'DAVIA',
    'offerType' => 'diploma',
    'cohortWpId' => 100,
    'cohortNumber' => 10,
    'cohortName' => 'Cohorte 10',
    'offerStatus' => 'abierta',
    'startDate' => '2027-04-08',
    'startDatePrecision' => 'day',
    'modality' => 'hibrida',
    'preinscripcionUrl' => 'https://preinscripciones.flacso.edu.uy/davia-c10',
];

$snapshot = FLACSO_Inquiry_Snapshot::from_offer($form, $context, 'q-1');
snapshot_assert($snapshot['academic']['startDate'] === '2027-04-08', 'fecha canónica');
snapshot_assert($snapshot['academic']['modality'] === 'hibrida', 'modalidad canónica');
snapshot_assert($snapshot['links']['cartaUrl'] === 'https://flacso.edu.uy/davia/carta', 'deriva URL de carta desde la oferta');
snapshot_assert($snapshot['tags'] === ['interes-davia', 'davia-c10', 'origen-web-consultas'], 'tags canónicos únicos');
snapshot_assert(strpos(json_encode($snapshot), 'Este texto') === false, 'el texto libre no pertenece al snapshot');

$payload = FLACSO_Mautic_Payload_Builder::build($snapshot);
snapshot_assert(!array_key_exists('flacso_consulta_texto', $payload['fields']), 'no envía flacso_consulta_texto');
snapshot_assert(!array_key_exists('flacso_oferta_nombre', $payload['fields']), 'datos de consulta no se escriben en el contacto');
snapshot_assert($payload['tokens']['{oferta_academica_nombre}'] === 'Diploma DAVIA', 'tokens salen del snapshot');
snapshot_assert($payload['tokens']['{fecha_inicio}'] === '8 de abril de 2027', 'fecha del token sale del snapshot');
snapshot_assert($payload['tokens']['{modalidad}'] === 'Híbrida', 'modalidad del token sale del snapshot');
$expected_email_tokens = [
    '{nombre}' => 'Ana',
    '{apellido}' => 'Pérez',
    '{correo}' => 'ana@example.org',
    '{pais}' => 'Uruguay',
    '{profesion}' => 'Docente',
    '{nivel_academico}' => 'Posgrado',
    '{oferta_academica_articulo}' => '',
    '{oferta_academica_fecha_inicio}' => '8 de abril de 2027',
    '{oferta_academica_modalidad}' => 'Híbrida',
    '{oferta_academica_nombre}' => 'Diploma DAVIA',
    '{url_carta}' => 'https://flacso.edu.uy/davia/carta',
    '{url_preinscripcion}' => 'https://preinscripciones.flacso.edu.uy/davia-c10',
];
foreach ($expected_email_tokens as $token => $expected_value) {
    snapshot_assert(($payload['tokens'][$token] ?? null) === $expected_value, "token {$token} llega a Mautic");
}

$context2 = $context;
$context2['offerName'] = 'Maestría MG';
$context2['offerAbbreviation'] = 'MG';
$context2['cohortNumber'] = 3;
$context2['startDate'] = '2027-08-01';
$context2['modality'] = 'virtual';
$form2 = $form;
$form2['programUrl'] = 'https://flacso.edu.uy/mg/';
$snapshot2 = FLACSO_Inquiry_Snapshot::from_offer($form2, $context2, 'q-2');

$tokens1 = FLACSO_Inquiry_Snapshot::delivery_tokens($snapshot);
$tokens2 = FLACSO_Inquiry_Snapshot::delivery_tokens($snapshot2);
snapshot_assert($tokens1['{programa}'] === 'Diploma DAVIA', 'primer snapshot permanece intacto');
snapshot_assert($tokens2['{programa}'] === 'Maestría MG', 'segundo snapshot independiente');
snapshot_assert($tokens1['{modalidad}'] === 'Híbrida' && $tokens2['{modalidad}'] === 'Virtual', 'dos consultas del mismo contacto no se pisan');
snapshot_assert($tokens2['{url_carta}'] === 'https://flacso.edu.uy/mg/carta', 'url_carta evita barras dobles');

echo "OK inquiry-snapshot-test\n";
