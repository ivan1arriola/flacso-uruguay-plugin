<?php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

class FLACSO_Counting_PDO extends PDO {
    public int $statement_count = 0;

    public function __construct() {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function prepare(string $query, array $options = []) {
        $this->statement_count++;
        return parent::prepare($query, $options);
    }

    public function query(string $query, ?int $fetch_mode = null, ...$fetch_mode_args) {
        $this->statement_count++;
        if ($fetch_mode === null) {
            return parent::query($query);
        }
        return parent::query($query, $fetch_mode, ...$fetch_mode_args);
    }
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-analytics-repository.php';

function analytics_plan_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = new FLACSO_Counting_PDO();
$pdo->exec('CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT, offerName TEXT, offerWpId INTEGER,
    email TEXT, emailNormalized TEXT, firstName TEXT, lastName TEXT, fullName TEXT,
    country TEXT, source TEXT, campaignProvider TEXT, campaignName TEXT, campaignSource TEXT, campaignMedium TEXT,
    campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
    emailStatus TEXT, emailSender TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT,
    inquiryAt TEXT, offerAbbreviation TEXT, cohortNumber INTEGER, cohortName TEXT,
    mauticSyncStatus TEXT, mauticContactId TEXT, mauticSyncedAt TEXT, mauticLastError TEXT,
    followupStatus TEXT, followupDueAt TEXT, followupSentAt TEXT, followupAttempts INTEGER,
    followupLastError TEXT, offerStatus TEXT
)');
$rows = [
    ['a1', 'Programa A', 'ana@example.org', '2026-10-01 10:00:00'],
    ['a2', 'Programa A', 'ana@example.org', '2026-10-02 10:00:00'],
    ['b1', 'Programa B', 'bea@example.org', '2026-10-03 10:00:00'],
];
$insert = $pdo->prepare('INSERT INTO offer_inquiries (id, consultaId, offerName, email, emailNormalized, fullName, country, emailStatus, inquiryAt)
    VALUES (:id, :consulta, :offer, :email, :normalized, :name, :country, "sent", :at)');
foreach ($rows as [$id, $offer, $email, $at]) {
    $insert->execute([
        ':id' => $id,
        ':consulta' => 'consulta-' . $id,
        ':offer' => $offer,
        ':email' => $email,
        ':normalized' => $email,
        ':name' => ucfirst($email),
        ':country' => 'Uruguay',
        ':at' => $at,
    ]);
}

FLACSO_DB::set_connection($pdo);
$before = $pdo->statement_count;
$result = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries([
    'table' => 'offer_inquiries',
    'mode' => 'grouped',
    'page' => 1,
    'page_size' => 25,
]);
$used = $pdo->statement_count - $before;

analytics_plan_assert(count($result['items']) === 2, 'la vista agrupada conserva los dos grupos');
$program_a = array_values(array_filter($result['items'], static fn(array $item): bool => $item['item_name'] === 'Programa A'))[0] ?? [];
analytics_plan_assert(($program_a['count'] ?? 0) === 2, 'el grupo Programa A conserva su cantidad');
analytics_plan_assert(count($program_a['children'] ?? []) === 2, 'el grupo Programa A conserva sus hijos');
analytics_plan_assert(($program_a['children'][0]['id'] ?? '') === 'a2', 'los hijos conservan orden descendente');
analytics_plan_assert($result['pageInfo']['totalItems'] === 2, 'la paginación conserva el total de grupos');
analytics_plan_assert($used <= 6, 'la vista agrupada no debe ejecutar una consulta hija por grupo');

$summary_before = $pdo->statement_count;
$summary_first = FLACSO_Inquiry_Analytics_Repository::get_analytics_summary('2026-10-01', '2026-10-03', '', 'offer_inquiries');
$summary_after_first = $pdo->statement_count;
$summary_second = FLACSO_Inquiry_Analytics_Repository::get_analytics_summary('2026-10-01', '2026-10-03', '', 'offer_inquiries');
analytics_plan_assert($summary_second === $summary_first, 'el resumen cacheado conserva exactamente su respuesta');
analytics_plan_assert($pdo->statement_count === $summary_after_first, 'el segundo resumen idéntico no debe consultar PostgreSQL');
analytics_plan_assert($summary_after_first > $summary_before, 'el primer resumen debe consultar PostgreSQL');

$empty = FLACSO_Inquiry_Analytics_Repository::get_paginated_inquiries([
    'table' => 'offer_inquiries',
    'mode' => 'grouped',
    'page' => 5,
    'page_size' => 25,
]);
analytics_plan_assert($empty['items'] === [], 'una página vacía conserva items vacío');

echo "OK inquiry-analytics-query-plan-test\n";
