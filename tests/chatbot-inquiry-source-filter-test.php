<?php
/** Regresión de filtros por origen en modo individual y agrupado. */
$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-analytics-repository.php';

function check_source_filter(bool $value, string $message): void {
    if (!$value) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT, offerName TEXT, offerWpId INTEGER,
    firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT,
    country TEXT, source TEXT, emailStatus TEXT, emailSender TEXT,
    mailjetMessageId TEXT, mailjetMessageUuid TEXT,
    inquiryAt TEXT, createdAt TEXT, offerStatus TEXT
)');
$insert = $pdo->prepare('INSERT INTO offer_inquiries
 (id, consultaId, offerName, offerWpId, firstName, lastName, fullName, email, emailNormalized,
 country, source, emailStatus, emailSender, inquiryAt, createdAt, offerStatus)
 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$rows = [
    ['one','c-one','Diploma en Género',10,'Ana','Pérez','Ana Pérez','ana@example.com','ana@example.com','Uruguay','Web - Formulario dinámico','pending','mautic','2026-10-13T10:00:00Z','2026-10-13T10:00:00Z','abierta'],
    ['two','c-two','Diploma en Género',10,'Ana','Pérez','Ana Pérez','ana@example.com','ana@example.com','Uruguay','Instagram','pending','mautic','2026-10-13T11:00:00Z','2026-10-13T11:00:00Z','abierta'],
    ['three','c-three','Seminario Educativo',20,'Ben','Paz','Ben Paz','ben@example.com','ben@example.com','Uruguay','Facebook Messenger','pending','mautic','2026-10-11T11:00:00Z','2026-10-11T11:00:00Z','sin_cohorte'],
];
foreach ($rows as $row) $insert->execute($row);
FLACSO_DB::set_connection($pdo);
FLACSO_Inquiry_Analytics_Repository::clear_cache();

$base = ['table' => 'offer_inquiries', 'page_size' => 25];
foreach (['raw', 'grouped'] as $mode) {
    $all = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries($base + ['mode' => $mode]);
    check_source_filter($all['pageInfo']['totalItems'] === ($mode === 'raw' ? 3 : 2), "Conteos sin filtro en {$mode}");

    $ig = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries($base + ['mode' => $mode, 'source' => 'instagram']);
    check_source_filter($ig['pageInfo']['totalItems'] === 1, "Instagram en {$mode}");
    check_source_filter($ig['items'][0]['source'] === 'Instagram', "Origen en fila Instagram {$mode}");
    check_source_filter(count($ig['items'][0]['children']) === 1, "Hijos Instagram {$mode}");

    $web = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries($base + ['mode' => $mode, 'source' => 'web']);
    check_source_filter($web['pageInfo']['totalItems'] === 1, "Web en {$mode}");

    $messenger = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries($base + ['mode' => $mode, 'source' => 'messenger']);
    check_source_filter($messenger['pageInfo']['totalItems'] === 1, "Messenger en {$mode}");

    $range = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries($base + [
        'mode' => $mode, 'desde' => '2026-10-13', 'hasta' => '2026-10-13'
    ]);
    check_source_filter($range['pageInfo']['totalItems'] === ($mode === 'raw' ? 2 : 1), "Rango de fecha {$mode}");
    if ($mode === 'grouped') {
        check_source_filter(count($range['items'][0]['children']) === 2, 'Consulta agrupada conserva ambos canales como hijos');
    }
}
echo "OK chatbot source filter\n";
