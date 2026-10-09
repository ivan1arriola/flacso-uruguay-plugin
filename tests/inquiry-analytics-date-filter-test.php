<?php

$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-analytics-repository.php';

function analytics_date_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT, offerName TEXT, offerWpId INTEGER,
    email TEXT, emailNormalized TEXT, fullName TEXT, country TEXT,
    emailStatus TEXT, emailSender TEXT, inquiryAt TEXT, createdAt TEXT,
    offerStatus TEXT
)');

$insert = $pdo->prepare('INSERT INTO offer_inquiries
    (id, consultaId, offerName, email, emailNormalized, fullName, country, emailStatus, inquiryAt, createdAt, offerStatus)
    VALUES (:id, :consulta, :offer, :email, :normalized, :name, :country, :status, :inquiry, :created, :offer_status)');
$insert->execute([
    ':id' => 'iso',
    ':consulta' => 'consulta-iso',
    ':offer' => 'Oferta ISO',
    ':email' => 'iso@example.org',
    ':normalized' => 'iso@example.org',
    ':name' => 'Persona ISO',
    ':country' => 'Uruguay',
    ':status' => 'accepted',
    ':inquiry' => '2026-10-09T12:00:00.000Z',
    ':created' => '2026-10-09T12:00:00.000Z',
    ':offer_status' => 'abierta',
]);
$insert->execute([
    ':id' => 'fallback',
    ':consulta' => 'consulta-fallback',
    ':offer' => 'Oferta histórica',
    ':email' => 'old@example.org',
    ':normalized' => 'old@example.org',
    ':name' => 'Persona histórica',
    ':country' => 'Uruguay',
    ':status' => 'accepted',
    ':inquiry' => null,
    ':created' => '2026-10-08T12:00:00.000Z',
    ':offer_status' => 'sin_cohorte',
]);

FLACSO_DB::set_connection($pdo);
FLACSO_Inquiry_Analytics_Repository::clear_cache();
$result = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries([
    'table' => 'offer_inquiries',
    'mode' => 'raw',
    'desde' => '2026-10-08',
    'hasta' => '2026-10-09',
    'page_size' => 25,
]);

analytics_date_assert($result['pageInfo']['totalItems'] === 2, 'el rango incluye timestamps ISO y usa createdAt cuando inquiryAt es nulo');

$grouped = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries([
    'table' => 'offer_inquiries',
    'mode' => 'grouped',
    'desde' => '2026-10-08',
    'hasta' => '2026-10-09',
    'page_size' => 25,
]);
analytics_date_assert($grouped['pageInfo']['totalItems'] === 2, 'la vista agrupada conserva los resultados cuando aplica rango de fechas');

echo "OK inquiry-analytics-date-filter-test\n";
