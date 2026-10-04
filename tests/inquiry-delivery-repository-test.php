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
delivery_repo_assert((int) $row['templateId'] === 4, 'oferta abierta usa F1 #4');
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

// Una oferta cerrada debe persistir F1 #5 y un blocked comprobado se puede
// reservar manualmente sin habilitar reenvíos de accepted/acceptance_unknown.
$closed_context = $context;
$closed_context['offerStatus'] = 'cerrada';
$closed_snapshot = FLACSO_Inquiry_Snapshot::from_offer($form, $closed_context, 'manual-retry-closed');
$closed_record = $record;
$closed_record['consultaId'] = 'manual-retry-closed';
$closed_record['offerStatus'] = 'cerrada';
$closed_result = $deliveries->persist_inquiry_with_delivery($source, $closed_record, $closed_snapshot, 'offer');
$closed_row = $deliveries->find_by_delivery_id($closed_result['delivery_id']);
delivery_repo_assert((int) ($closed_row['templateId'] ?? 0) === 5, 'oferta cerrada usa F1 #5');

// Simular una entrega histórica creada cuando todas las consultas usaban #4.
$pdo->prepare('UPDATE inquiry_deliveries SET templateId = 4 WHERE id = :id')
    ->execute([':id' => $closed_result['delivery_id']]);
$pdo->prepare('UPDATE inquiry_deliveries SET state = :state WHERE id = :id')
    ->execute([':state' => 'blocked', ':id' => $closed_result['delivery_id']]);
$refreshed_closed = $deliveries->refresh_template_identity($closed_result['delivery_id']);
delivery_repo_assert((int) ($refreshed_closed['templateId'] ?? 0) === 5, 'reintento corrige una entrega histórica cerrada de #4 a #5');
delivery_repo_assert($deliveries->claim_manual_retry($closed_result['delivery_id'], 60) === true, 'blocked histórico admite reserva manual');
$pdo->prepare('UPDATE inquiry_deliveries SET state = :state, claimedAt = NULL, claimedUntil = NULL, claimToken = NULL WHERE id = :id')
    ->execute([':state' => 'pending', ':id' => $closed_result['delivery_id']]);

$closed_claimed = $deliveries->claim_pending_batch(10, 60);
delivery_repo_assert(count($closed_claimed) === 1, 'worker reclama entrega cerrada');
delivery_repo_assert($deliveries->mark_blocked($closed_result['delivery_id'], 'mautic_contract', 'prueba') === true, 'entrega puede quedar blocked');
delivery_repo_assert($deliveries->claim_manual_retry($closed_result['delivery_id'], 60) === true, 'blocked comprobado admite reintento manual');
$manual_retry_row = $deliveries->find_by_delivery_id($closed_result['delivery_id']);
delivery_repo_assert(($manual_retry_row['state'] ?? '') === 'processing', 'reintento manual reserva la entrega');
delivery_repo_assert($deliveries->claim_manual_retry($result['delivery_id'], 60) === false, 'accepted nunca admite reintento manual');

$expired_terminal = gmdate('c', time() - (91 * 86400));
$stmt = $pdo->prepare('UPDATE inquiry_deliveries SET terminalAt = :terminal_at');
$stmt->execute([':terminal_at' => $expired_terminal]);
$anonymized = $deliveries->anonymize_due_deliveries(90);
delivery_repo_assert($anonymized === 1, 'retención anonimiza entrega vencida');
$anon = $pdo->query('SELECT * FROM inquiry_deliveries LIMIT 1')->fetch(PDO::FETCH_ASSOC);
delivery_repo_assert($anon['snapshotId'] === null && $anon['consultaId'] === null && $anon['email'] === null, 'retención rompe vínculos reversibles');
delivery_repo_assert($anon['payloadJson'] === null && $anon['contactId'] === null, 'retención elimina payload/contacto');
delivery_repo_assert((int) $pdo->query("SELECT COUNT(*) FROM inquiry_snapshots WHERE consultaId = 'atomic-1'")->fetchColumn() === 0, 'retención elimina el snapshot de la entrega terminal vencida');
delivery_repo_assert((int) $pdo->query("SELECT COUNT(*) FROM inquiry_snapshots WHERE consultaId = 'manual-retry-closed'")->fetchColumn() === 1, 'retención conserva snapshot de una entrega que sigue en processing');
delivery_repo_assert($anon['reportMonth'] === '2026-09-01', 'retención conserva mes agregado');

echo "OK inquiry-delivery-repository-test\n";
