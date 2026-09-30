<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

function payload_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/modules/consultas/services/class-flacso-mautic-payload-builder.php';

$open = FLACSO_Mautic_Payload_Builder::build([
    'id' => 'q-1', 'inquiryAt' => '2026-09-30T12:00:00+00:00', 'email' => 'ana@example.org',
    'firstName' => 'Ana', 'lastName' => 'Perez', 'offerType' => 'oferta',
    'offerAbbreviation' => 'DAVIA', 'offerName' => 'Diploma', 'cohortNumber' => 10,
    'cohortName' => 'Cohorte 10', 'offerStatus' => 'abierta', 'modalidad' => 'Virtual',
    'fechaInicio' => '2027-04-08', 'preinscripcionUrl' => 'https://flacso.edu.uy/pre', 'message' => 'Hola',
    'country' => 'Uruguay', 'education_level' => 'Título universitario', 'profession' => 'Docente',
]);
payload_assert($open['fields']['flacso_cohorte_estado'] === 'abierta', 'normaliza estado');
payload_assert($open['fields']['flacso_modalidad'] === 'virtual', 'normaliza modalidad');
payload_assert($open['fields']['flacso_cohorte_codigo'] === 'davia-c10', 'crea codigo cohorte');
payload_assert($open['fields']['flacso_pais'] === 'Uruguay', 'mapea país');
payload_assert($open['fields']['flacso_nivel_academico'] === 'Título universitario', 'mapea nivel académico');
payload_assert($open['fields']['flacso_profesion'] === 'Docente', 'mapea profesión');
payload_assert($open['tags'] === ['interes-davia', 'davia-c10', 'origen-web-consultas'], 'crea tags canonicos');

$missing = FLACSO_Mautic_Payload_Builder::build(['id' => 'q-2', 'offerType' => 'seminario', 'offerStatus' => 'CERRADA', 'modalidad' => 'mixta', 'fechaInicio' => '2027']);
payload_assert($missing['fields']['flacso_cohorte_estado'] === 'cerrada', 'acepta estado historico normalizado');
payload_assert($missing['fields']['flacso_modalidad'] === '', 'omite modalidad no canonica');
payload_assert($missing['fields']['flacso_fecha_inicio'] === '', 'omite fecha sin precision');
payload_assert($missing['fields']['flacso_pais'] === '', 'omite país faltante');
payload_assert($missing['fields']['flacso_nivel_academico'] === '', 'omite nivel académico faltante');
payload_assert($missing['fields']['flacso_profesion'] === '', 'omite profesión faltante');
payload_assert($missing['tags'] === ['origen-web-consultas'], 'sin codigo no inventa tags');

echo "OK mautic payload builder\n";
