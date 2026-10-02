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

$root = dirname(__DIR__);
require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-payload-builder.php';

$snapshot = FLACSO_Inquiry_Snapshot::from_offer(
    [
        'inquiryAt' => '2026-09-30T12:00:00+00:00',
        'email' => 'ana@example.org',
        'firstName' => 'Ana',
        'lastName' => 'Perez',
        'country' => 'Uruguay',
        'educationLevel' => 'Título universitario',
        'profession' => 'Docente',
        'programUrl' => 'https://flacso.edu.uy/diploma/',
        'preinscripcionUrl' => 'https://flacso.edu.uy/pre',
        'message' => 'No sale de WordPress',
    ],
    [
        'offerType' => 'oferta',
        'offerAbbreviation' => 'DAVIA',
        'offerName' => 'Diploma',
        'cohortNumber' => 10,
        'cohortName' => 'Cohorte 10',
        'offerStatus' => 'abierta',
        'modality' => 'virtual',
        'startDate' => '2027-04-08',
        'startDatePrecision' => 'day',
    ],
    'q-1'
);

$built = FLACSO_Mautic_Payload_Builder::build($snapshot);
payload_assert($built['fields']['flacso_pais'] === 'Uruguay', 'mapea país estable');
payload_assert($built['fields']['flacso_nivel_academico'] === 'Título universitario', 'mapea nivel académico estable');
payload_assert($built['fields']['flacso_profesion'] === 'Docente', 'mapea profesión estable');
payload_assert($built['tags'] === ['interes-davia', 'davia-c10', 'origen-web-consultas'], 'tags canónicos');
payload_assert($built['tokens']['{programa}'] === 'Diploma', 'tokens de consulta salen del snapshot');
payload_assert($built['tokens']['{fecha_inicio}'] === '8 de abril de 2027', 'fecha de token canónica');
payload_assert(!array_key_exists('flacso_consulta_texto', $built['fields']), 'texto libre excluido');
payload_assert(!array_key_exists('flacso_oferta_codigo', $built['fields']), 'oferta no se persiste como estado mutable del contacto');
payload_assert(!array_key_exists('flacso_cohorte_codigo', $built['fields']), 'cohorte no se persiste como estado mutable del contacto');

echo "OK mautic payload builder\n";
