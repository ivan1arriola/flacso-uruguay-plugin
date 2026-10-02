<?php
$root = dirname(__DIR__);
require_once __DIR__ . '/support/inquiry-delivery-bootstrap.php';
require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-base-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';

function delivery_repo_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = flacso_test_delivery_pdo();
FLACSO_DB::set_connection($pdo);
$source = new FLACSO_Offer_Inquiry_Repository();
$deliveries = new FLACSO_Inquiry_Delivery_Repository($pdo);

$form = [
    'nombre' => 'Ana',
    'apellido' => 'Pérez',
    'correo' => 'ana@example.org',
    'programUrl' => 'https://flacso.edu.uy/davia',
    'inquiryAt' => '2026-09-30T12:00:00+00:00',
];
$context = [
    'offerWpId' => 10,
    'offerName' => 'DAVIA',
    'offerAbbreviation' => 'DAVIA',
    'offerType' => 'diploma',
    'cohortNumber' => 10,
    'offerStatus' => 'abierta',
    'startDate' => '2027-04-08',
    'startDatePrecision' => 'day',
    'modality' => 'virtual',
];
$snapshot = FLACSO_Inquiry_Snapshot::from_offer($form, $context, 'atomic-1');
$record = [
    'consultaId' => 'atomic-1',
    'offerWpId' => 10,
    'offerName' => 'DAVIA',
    'offerAbbreviation' => 'davia',
    'firstName' => 'Ana',
    'lastName' => 'Pérez',
    'email' => 'ana@example.org',
    'inquiryAt' => '2026-09-30T12:00:00+00:00',
    'emailStatus' => 'pending',
    'emailSender' => 'mautic_transactional_queue',
    'payload' => ['consulta' => 'permanece sólo en WordPress'],
];

$result = $deliveries->persist_inquiry_with_delivery($source, $record, $snapshot, 'offer');
delivery_repo_assert($result['duplicate'] === false, 'primera persistencia no es duplicada');
delivery_repo_assert((int) $pdo->query('SELECT COUNT(*) FROM offer_inquiries')->fetchColumn() === 1, 'una consulta');
delivery_repo_assert((int) $pdo->query('SELECT COUNT(*) FROM inquiry_snapshots')->fetchColumn() === 1, 'un snapshot');
delivery_repo_assert((int) $pdo->query('SELECT COUNT(*) FROM inquiry_deliveries')->fetchColumn() === 1, 'una entrega');

$row = $deliveries->find_by_delivery_id($result['delivery_id']);
delivery_repo_assert($row !== null && $row['state'] === 'pending', 'entrega pending');
delivery_repo_assert(strpos((string) $row['payloadJson'], 'permanece sólo') === false, 'payload de entrega no contiene texto libre');
delivery_repo_assert(strpos((string) $row['snapshotJson'], 'permanece sólo') === false, 'snapshot no contiene texto libre');

$duplicate = $deliveries->persist_inquiry_with_delivery($source, $record, $snapshot, 'offer');
delivery_repo_assert($duplicate['duplicate'] === true, 'consulta repetida es idempotente');
delivery_repo_assert((int) $pdo->query('SELECT COUNT(*) FROM inquiry_snapshots')->fetchColumn() === 1, 'duplicado no crea snapshot');
delivery_repo_assert((int) $pdo->query('SELECT COUNT(*) FROM inquiry_deliveries')->fetchColumn() === 1, 'duplicado no crea entrega');

// Rollback: eliminamos la tabla de entregas después de crear un segundo snapshot potencial.
$pdo->exec('DROP TABLE inquiry_deliveries');
$failed = false;
try {
    $snapshot2 = FLACSO_Inquiry_Snapshot::from_offer($form, $context, 'atomic-rollback');
    $record2 = $record;
    $record2['consultaId'] = 'atomic-rollback';
    $deliveries->persist_inquiry_with_delivery($source, $record2, $snapshot2, 'offer');
} catch (Throwable $e) {
    $failed = true;
}
delivery_repo_assert($failed, 'fallo de entrega propaga excepción');
delivery_repo_assert((int) $pdo->query("SELECT COUNT(*) FROM offer_inquiries WHERE consultaId = 'atomic-rollback'")->fetchColumn() === 0, 'rollback elimina consulta');
delivery_repo_assert((int) $pdo->query("SELECT COUNT(*) FROM inquiry_snapshots WHERE consultaId = 'atomic-rollback'")->fetchColumn() === 0, 'rollback elimina snapshot');

// Reponer tablas de entrega para probar reserva y retención.
$pdo = flacso_test_delivery_pdo();
FLACSO_DB::set_connection($pdo);
$source = new FLACSO_Offer_Inquiry_Repository();
$deliveries = new FLACSO_Inquiry_Delivery_Repository($pdo);
$result = $deliveries->persist_inquiry_with_delivery($source, $record, $snapshot, 'offer');

$claimed1 = $deliveries->claim_pending_batch(10, 60);
$claimed2 = $deliveries->claim_pending_batch(10, 60);
delivery_repo_assert(count($claimed1) === 1, 'primer worker reclama entrega');
delivery_repo_assert(count($claimed2) === 0, 'segundo worker no reclama la misma entrega');
$attempt_id = bin2hex(random_bytes(16));
$attempt_row_id = $deliveries->record_attempt_start($result['delivery_id'], $attempt_id);
delivery_repo_assert($attempt_row_id !== '', 'registra intento de entrega');
delivery_repo_assert((int) $pdo->query('SELECT attempts FROM inquiry_deliveries LIMIT 1')->fetchColumn() === 1, 'incrementa intentos de entrega');

$deliveries->mark_accepted($result['delivery_id'], 200);
$expired_terminal = gmdate('c', time() - (91 * 86400));
$stmt = $pdo->prepare('UPDATE inquiry_deliveries SET terminalAt = :terminal_at');
$stmt->execute([':terminal_at' => $expired_terminal]);
$anonymized = $deliveries->anonymize_due_deliveries(90);
delivery_repo_assert($anonymized === 1, 'retención anonimiza entrega vencida');
$anon = $pdo->query('SELECT * FROM inquiry_deliveries LIMIT 1')->fetch(PDO::FETCH_ASSOC);
delivery_repo_assert($anon['snapshotId'] === null && $anon['consultaId'] === null && $anon['email'] === null, 'retención rompe vínculos reversibles');
delivery_repo_assert($anon['payloadJson'] === null && $anon['contactId'] === null, 'retención elimina payload/contacto');
delivery_repo_assert((int) $pdo->query('SELECT COUNT(*) FROM inquiry_snapshots')->fetchColumn() === 0, 'retención elimina snapshot transaccional vencido');
delivery_repo_assert($anon['reportMonth'] === '2026-09-01', 'retención conserva mes agregado');

echo "OK inquiry-delivery-repository-test\n";
