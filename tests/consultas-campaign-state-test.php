<?php
/**
 * Bandeja de consultas: estado de campaña por oferta (filtro, conteos del aviso
 * y pantalla). El estado derivado debe ser el mismo en SQL y en la pantalla.
 */
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-analytics-repository.php';

function campaign_state_assert(bool $condition, string $message): void {
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
    email TEXT, emailNormalized TEXT, firstName TEXT, lastName TEXT, fullName TEXT,
    country TEXT, source TEXT, campaignName TEXT, campaignSource TEXT, campaignMedium TEXT,
    emailStatus TEXT, emailSender TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT,
    inquiryAt TEXT, offerStatus TEXT,
    mauticCampaignId INTEGER, mauticCampaignStatus TEXT DEFAULT "pending",
    mauticCampaignAttemptedAt TEXT, mauticCampaignLastError TEXT
)');

// id, estado de campaña guardado, campaña, nota, estado del acuse, estado esperado
$rows = [
    ['j1', 'joined', 7, null, 'accepted', 'joined'],
    ['j2', 'joined', 8, null, 'accepted', 'joined'],
    ['s1', 'skipped', null, 'sin_consentimiento', 'accepted', 'skipped'],
    ['f1', 'failed', null, 'contact.email inválido', 'failed', 'failed'],
    ['p1', 'pending', null, 'HTTP 503', 'retryable_failed', 'pending'],
    ['p2', 'pending', null, null, 'pending', 'pending'],
    ['l1', 'pending', null, null, 'accepted', 'legacy'],
    ['l2', 'pending', null, null, 'sent', 'legacy'],
];
$insert = $pdo->prepare('INSERT INTO offer_inquiries
    (id, consultaId, offerName, email, emailNormalized, fullName, country, emailStatus, inquiryAt,
     mauticCampaignStatus, mauticCampaignId, mauticCampaignLastError)
    VALUES (:id, :consulta, "Maestría", :email, :email, :id, "Uruguay", :email_status, :at, :status, :campaign, :note)');
foreach ($rows as $index => [$id, $status, $campaign, $note, $email_status]) {
    $insert->execute([
        ':id' => $id,
        ':consulta' => 'c-' . $id,
        ':email' => $id . '@example.org',
        ':email_status' => $email_status,
        ':at' => '2026-10-' . sprintf('%02d', 10 + $index) . ' 10:00:00',
        ':status' => $status,
        ':campaign' => $campaign,
        ':note' => $note,
    ]);
}
FLACSO_DB::set_connection($pdo);

// Conteos del aviso
$counts = FLACSO_Inquiry_Analytics_Repository::campaign_state_counts('2026-10-01');
campaign_state_assert($counts === ['joined' => 2, 'skipped' => 1, 'failed' => 1, 'pending' => 2, 'legacy' => 2], 'los conteos agrupan cada consulta en un solo estado');
$since = FLACSO_Inquiry_Analytics_Repository::campaign_state_counts('2026-10-15');
campaign_state_assert($since['joined'] === 0 && $since['pending'] === 1 && $since['legacy'] === 2, 'los conteos respetan la fecha desde');

// Filtro de la bandeja, en vista individual y agrupada
foreach (['raw', 'grouped'] as $mode) {
    foreach (FLACSO_Inquiry_Analytics_Repository::CAMPAIGN_STATES as $state) {
        $expected = count(array_filter($rows, static fn(array $r): bool => $r[5] === $state));
        $result = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries([
            'table' => 'offer_inquiries', 'mode' => $mode, 'campaign_status' => $state, 'page' => 1, 'page_size' => 25,
        ]);
        campaign_state_assert($result['pageInfo']['totalItems'] === $expected, "filtro {$state} ({$mode}) devuelve {$expected} consultas");
    }
}
$all = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries(['table' => 'offer_inquiries', 'mode' => 'raw', 'page' => 1, 'page_size' => 25]);
campaign_state_assert($all['pageInfo']['totalItems'] === count($rows), 'sin filtro se ven todas');
$ignored = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries(['table' => 'offer_inquiries', 'mode' => 'raw', 'campaign_status' => 'x; DROP', 'page' => 1, 'page_size' => 25]);
campaign_state_assert($ignored['pageInfo']['totalItems'] === count($rows), 'un estado desconocido se ignora');
$joined = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries(['table' => 'offer_inquiries', 'mode' => 'raw', 'campaign_status' => 'joined', 'page' => 1, 'page_size' => 25]);
campaign_state_assert((int) $joined['items'][0]['mauticCampaignId'] > 0 && array_key_exists('mauticCampaignLastError', $joined['items'][0]), 'la bandeja trae la campaña y la nota de cada consulta');

// Sin columnas de campaña (base sin migrar): no falla ni filtra
$old = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$old->exec('CREATE TABLE offer_inquiries (id TEXT PRIMARY KEY, consultaId TEXT, offerName TEXT, email TEXT, emailNormalized TEXT, fullName TEXT, firstName TEXT, lastName TEXT, country TEXT, source TEXT, campaignName TEXT, campaignSource TEXT, campaignMedium TEXT, emailStatus TEXT, emailSender TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT, inquiryAt TEXT)');
$old->exec("INSERT INTO offer_inquiries (id, consultaId, offerName, email, emailNormalized, fullName, emailStatus, inquiryAt) VALUES ('o1', 'c-o1', 'X', 'o@example.org', 'o@example.org', 'O', 'sent', '2026-10-01 10:00:00')");
FLACSO_DB::set_connection($old);
campaign_state_assert(FLACSO_Inquiry_Analytics_Repository::campaign_state_counts('2026-10-01') === [], 'sin columnas de campaña no hay conteos');
$legacy = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries(['table' => 'offer_inquiries', 'mode' => 'raw', 'campaign_status' => 'joined', 'page' => 1, 'page_size' => 25]);
campaign_state_assert($legacy['pageInfo']['totalItems'] === 1, 'sin columnas de campaña el filtro no oculta consultas');

// Pantalla: mismas reglas, columna Campaña, aviso y respaldo transaccional
$admin = file_get_contents($root . '/modules/consultas/includes/class-flacso-consultas-admin.php');
$required = [
    'public static function campaign_state(' => 'la pantalla deriva el estado de campaña',
    'public static function render_campaign_badge(' => 'hay badge de campaña',
    'public static function render_delivery_cell(' => 'la celda del correo distingue campaña y respaldo',
    '<th>Campaña</th>' => 'la columna Mautic pasa a ser Campaña',
    'name="campaign_status"' => 'hay filtro por campaña',
    "'campaign_status' => \$campaign_status" => 'el filtro llega al repositorio',
    'Campañas por oferta' => 'el aviso superior habla de campañas',
    'Acuse transaccional (respaldo)' => 'el acuse transaccional queda como respaldo',
    'self::render_transactional_diagnostics();' => 'el aviso sigue renderizándose en la plataforma',
];
foreach ($required as $needle => $message) {
    campaign_state_assert(strpos($admin, $needle) !== false, $message);
}
campaign_state_assert(strpos($admin, "'joined' !== self::campaign_state( \$row )") !== false, 'no se ofrece reenvío transaccional a quien está en campaña');

echo "OK consultas-campaign-state-test\n";
