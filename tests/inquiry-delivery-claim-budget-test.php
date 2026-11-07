<?php
/**
 * Presupuesto de reclamos, recuperación de reservas vencidas y propiedad del
 * claim de las entregas de consultas.
 */
$root = dirname(__DIR__);
require_once __DIR__ . '/support/inquiry-delivery-bootstrap.php';
require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-base-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';

function budget_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = flacso_test_delivery_pdo();
FLACSO_DB::set_connection($pdo);
$source = new FLACSO_Offer_Inquiry_Repository();
$deliveries = new FLACSO_Inquiry_Delivery_Repository($pdo);

$form = ['nombre' => 'Ana', 'apellido' => 'Pérez', 'correo' => 'ana@example.org', 'programUrl' => 'https://flacso.edu.uy/davia', 'inquiryAt' => '2026-09-30T12:00:00+00:00'];
$context = ['offerWpId' => 10, 'offerName' => 'DAVIA', 'offerAbbreviation' => 'DAVIA', 'offerType' => 'diploma', 'cohortNumber' => 10, 'offerStatus' => 'abierta', 'startDate' => '2027-04-08', 'startDatePrecision' => 'day', 'modality' => 'virtual'];

function budget_delivery(FLACSO_Inquiry_Delivery_Repository $deliveries, FLACSO_Offer_Inquiry_Repository $source, array $form, array $context, string $id): string {
    $snapshot = FLACSO_Inquiry_Snapshot::from_offer($form, $context, $id);
    $record = [
        'consultaId' => $id, 'offerWpId' => 10, 'offerName' => 'DAVIA', 'offerAbbreviation' => 'davia',
        'firstName' => 'Ana', 'lastName' => 'Pérez', 'email' => 'ana@example.org',
        'inquiryAt' => '2026-09-30T12:00:00+00:00', 'emailStatus' => 'pending', 'emailSender' => 'mautic_transactional_queue',
    ];
    return $deliveries->persist_inquiry_with_delivery($source, $record, $snapshot, 'offer')['delivery_id'];
}

function budget_row(PDO $pdo, string $id): array {
    $stmt = $pdo->prepare('SELECT * FROM inquiry_deliveries WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 1. Cada reclamo cuenta; una rama que reprograma sin intento externo termina por agotar el presupuesto.
$id = budget_delivery($deliveries, $source, $form, $context, 'budget-1');
for ($i = 1; $i <= FLACSO_Inquiry_Delivery_Repository::MAX_CLAIM_ATTEMPTS; $i++) {
    $claimed = $deliveries->claim_pending_batch(1, 60);
    budget_assert(count($claimed) === 1, "reclamo {$i} disponible");
    budget_assert((int) $claimed[0]['attempts'] === $i, "el reclamo {$i} incrementa attempts");
    budget_assert($deliveries->mark_retryable_failure($id, null, 'mautic_contract_read', 'x', 60, $claimed[0]['claimToken']), 'reprograma con su token');
    $pdo->prepare('UPDATE inquiry_deliveries SET nextAttemptAt = NULL WHERE id = :id')->execute([':id' => $id]);
}
budget_assert($deliveries->claim_pending_batch(1, 60) === [], 'agotado el presupuesto no se reclama más');
$row = budget_row($pdo, $id);
budget_assert($row['state'] === 'failed' && $row['lastErrorClass'] === 'attempts_exhausted', 'queda failed y recuperable por reintento manual');
budget_assert($deliveries->claim_manual_retry($id, 60) === true, 'failed admite reintento manual');

// 2. Entregas varadas con attempts 3..5 vuelven a ser reclamables.
$id2 = budget_delivery($deliveries, $source, $form, $context, 'budget-2');
$pdo->prepare("UPDATE inquiry_deliveries SET state = 'retryable_failed', attempts = 3, nextAttemptAt = NULL WHERE id = :id")->execute([':id' => $id2]);
$claimed = $deliveries->claim_pending_batch(1, 60);
budget_assert(count($claimed) === 1 && $claimed[0]['id'] === $id2, 'una entrega con tres intentos previos sigue reclamable');

// 3. Reserva vencida sin intento iniciado vuelve a pending; con intento iniciado queda incierta.
$id3 = budget_delivery($deliveries, $source, $form, $context, 'budget-3');
$claimed = $deliveries->claim_pending_batch(1, 60);
$past = gmdate('c', time() - 30);
$pdo->prepare('UPDATE inquiry_deliveries SET claimedUntil = :p WHERE id = :id')->execute([':p' => $past, ':id' => $id3]);
$deliveries->claim_pending_batch(1, 60);
$row = budget_row($pdo, $id3);
budget_assert($row['lastErrorClass'] === 'lease_expired_before_attempt', 'reserva sin intento se recupera');
budget_assert((int) $row['attempts'] >= 1, 'la recuperación conserva el contador');

$id4 = budget_delivery($deliveries, $source, $form, $context, 'budget-4');
$pdo->prepare("UPDATE inquiry_deliveries SET state = 'accepted' WHERE id != :id")->execute([':id' => $id4]);
$claimed = $deliveries->claim_pending_batch(1, 60);
budget_assert(count($claimed) === 1 && $claimed[0]['id'] === $id4, 'reclamo de la cuarta entrega');
$deliveries->record_attempt_start($id4, bin2hex(random_bytes(8)), $claimed[0]['claimToken']);
$pdo->prepare('UPDATE inquiry_deliveries SET claimedUntil = :p WHERE id = :id')->execute([':p' => gmdate('c', time() - 30), ':id' => $id4]);
$deliveries->claim_pending_batch(1, 60);
budget_assert(budget_row($pdo, $id4)['state'] === 'acceptance_unknown', 'reserva con intento iniciado queda incierta');

// 4. Propiedad del claim: un proceso suspendido no puede continuar tras perder la reserva.
$id5 = budget_delivery($deliveries, $source, $form, $context, 'budget-5');
$pdo->prepare("UPDATE inquiry_deliveries SET state = 'accepted' WHERE id != :id")->execute([':id' => $id5]);
$a = $deliveries->claim_pending_batch(1, 60)[0];
$pdo->prepare('UPDATE inquiry_deliveries SET claimedUntil = :p WHERE id = :id')->execute([':p' => gmdate('c', time() - 30), ':id' => $id5]);
$b = $deliveries->claim_pending_batch(1, 60)[0] ?? null;
budget_assert($b !== null && $b['claimToken'] !== $a['claimToken'], 'otro proceso reclama tras vencer la reserva');
$lost = false;
try {
    $deliveries->record_attempt_start($id5, bin2hex(random_bytes(8)), $a['claimToken']);
} catch (FLACSO_Delivery_Claim_Lost_Exception $e) {
    $lost = true;
}
budget_assert($lost, 'el proceso antiguo no puede iniciar un intento');
budget_assert($deliveries->mark_accepted($id5, 200, $a['claimToken']) === false, 'el proceso antiguo no puede cerrar la entrega');
budget_assert($deliveries->mark_accepted($id5, 200, $b['claimToken']) === true, 'el dueño actual sí puede cerrarla');

// 5. Inicio autorizado antes del vencimiento: la recuperación ya no puede devolver la entrega a la cola.
$id6 = budget_delivery($deliveries, $source, $form, $context, 'budget-6');
$pdo->prepare("UPDATE inquiry_deliveries SET state = 'accepted' WHERE id != :id")->execute([':id' => $id6]);
$a = $deliveries->claim_pending_batch(1, 60)[0];
$deliveries->record_attempt_start($id6, bin2hex(random_bytes(8)), $a['claimToken']);
$pdo->prepare('UPDATE inquiry_deliveries SET claimedUntil = :p WHERE id = :id')->execute([':p' => gmdate('c', time() - 30), ':id' => $id6]);
budget_assert($deliveries->claim_pending_batch(1, 60) === [], 'una entrega con envío autorizado no se vuelve a reclamar');
budget_assert(budget_row($pdo, $id6)['state'] === 'acceptance_unknown', 'queda incierta, nunca duplicable');

// 6. Reserva ya vencida al intentar iniciar: el UPDATE condicional no autoriza el envío.
$id7 = budget_delivery($deliveries, $source, $form, $context, 'budget-7');
$pdo->prepare("UPDATE inquiry_deliveries SET state = 'accepted' WHERE id != :id")->execute([':id' => $id7]);
$a = $deliveries->claim_pending_batch(1, 60)[0];
$pdo->prepare('UPDATE inquiry_deliveries SET claimedUntil = :p WHERE id = :id')->execute([':p' => gmdate('c', time() - 5), ':id' => $id7]);
$lost = false;
try {
    $deliveries->record_attempt_start($id7, bin2hex(random_bytes(8)), $a['claimToken']);
} catch (FLACSO_Delivery_Claim_Lost_Exception $e) {
    $lost = true;
}
budget_assert($lost, 'con la reserva vencida no se autoriza el envío');
budget_assert(budget_row($pdo, $id7)['attemptStartedAt'] === null, 'no queda marca de envío autorizado');
budget_assert((int) $pdo->query("SELECT COUNT(*) FROM inquiry_delivery_attempts WHERE deliveryId = '{$id7}'")->fetchColumn() === 0, 'no se registra ningún intento');

// 7. Rechazo determinista previo al envío: se libera la marca y la entrega no queda incierta.
$id8 = budget_delivery($deliveries, $source, $form, $context, 'budget-8');
$pdo->prepare("UPDATE inquiry_deliveries SET state = 'accepted' WHERE id != :id")->execute([':id' => $id8]);
$a = $deliveries->claim_pending_batch(1, 60)[0];
$attempt8 = bin2hex(random_bytes(8));
$deliveries->record_attempt_start($id8, $attempt8, $a['claimToken']);
budget_assert(budget_row($pdo, $id8)['attemptStartedAt'] !== null, 'el intento deja la marca');
// Igual que el servicio ante «campaña no disponible»: cierra el intento y libera la marca.
$deliveries->finish_attempt($attempt8, 'failed', 422, 'campaign_unavailable', 'sin campaña');
$deliveries->clear_attempt_started($id8, $a['claimToken']);
budget_assert(budget_row($pdo, $id8)['attemptStartedAt'] === null, 'un rechazo previo al envío libera la marca');
$pdo->prepare('UPDATE inquiry_deliveries SET claimedUntil = :p WHERE id = :id')->execute([':p' => gmdate('c', time() - 30), ':id' => $id8]);
$deliveries->claim_pending_batch(1, 60);
budget_assert(budget_row($pdo, $id8)['lastErrorClass'] === 'lease_expired_before_attempt' || budget_row($pdo, $id8)['state'] === 'processing', 'vuelve a la cola en vez de quedar incierta');

// 8. El reintento manual recibe presupuesto propio.
$id9 = budget_delivery($deliveries, $source, $form, $context, 'budget-9');
$pdo->prepare("UPDATE inquiry_deliveries SET state = 'failed', attempts = 6 WHERE id = :id")->execute([':id' => $id9]);
budget_assert($deliveries->claim_manual_retry($id9, 60) === true, 'failed admite reintento manual');
budget_assert((int) budget_row($pdo, $id9)['attempts'] === 1, 'el reintento manual reinicia el presupuesto');

// 9. Entrega heredada: intento registrado sin marca (versión anterior). Nunca se reclama sola.
$id10 = budget_delivery($deliveries, $source, $form, $context, 'budget-10');
$pdo->prepare("UPDATE inquiry_deliveries SET state = 'accepted' WHERE id != :id")->execute([':id' => $id10]);
$a = $deliveries->claim_pending_batch(1, 60)[0];
$pdo->prepare('INSERT INTO inquiry_delivery_attempts (id, deliveryId, attemptId, state, startedAt) VALUES (:i, :d, :a, :s, :t)')
    ->execute([':i' => 'legacy-attempt-1', ':d' => $id10, ':a' => bin2hex(random_bytes(8)), ':s' => 'started', ':t' => gmdate('c')]);
$pdo->prepare('UPDATE inquiry_deliveries SET claimedUntil = :p, attemptStartedAt = NULL WHERE id = :id')->execute([':p' => gmdate('c', time() - 30), ':id' => $id10]);
budget_assert($deliveries->claim_pending_batch(1, 60) === [], 'una entrega heredada con intento registrado no se reclama de nuevo');
budget_assert(budget_row($pdo, $id10)['state'] === 'acceptance_unknown', 'queda incierta para revisión, no se reenvía');

echo "OK inquiry-delivery-claim-budget-test\n";
