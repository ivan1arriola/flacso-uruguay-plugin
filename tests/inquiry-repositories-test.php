<?php
// tests/inquiry-repositories-test.php
$root = dirname(__DIR__);
require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-seminar-inquiry-repository.php';

function repo_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// Configurar SQLite en memoria con el esquema de tablas (solo offer_inquiries y seminar_inquiries)
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('
CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY,
    consultaId TEXT UNIQUE,
    offerWpId INTEGER,
    offerName TEXT,
    offerAbbreviation TEXT,
    offerType TEXT,
    cohortWpId INTEGER,
    cohortNumber INTEGER,
    cohortName TEXT,
    registrationOpenAt TEXT,
    registrationCloseAt TEXT,
    firstName TEXT,
    lastName TEXT,
    fullName TEXT,
    email TEXT,
    emailNormalized TEXT,
    country TEXT,
    profession TEXT,
    educationLevel TEXT,
    source TEXT DEFAULT "Web",
    campaignProvider TEXT,
    campaignSource TEXT,
    campaignMedium TEXT,
    campaignName TEXT,
    campaignExternalId TEXT,
    campaignContent TEXT,
    campaignTerm TEXT,
    urlBase TEXT,
    urlReferer TEXT,
    inquiryAt TEXT,
    ipAddress TEXT,
    userAgent TEXT,
    replyToEmail TEXT,
    programUrl TEXT,
    cartaUrl TEXT,
    preinscripcionUrl TEXT,
    offerStatus TEXT DEFAULT "sin_cohorte",
    mauticContactId TEXT,
    mauticSyncStatus TEXT DEFAULT "skipped",
    mauticSyncedAt TEXT,
    mauticLastError TEXT,
    followupDueAt TEXT,
    followupStatus TEXT DEFAULT "none",
    followupSentAt TEXT,
    followupAttempts INTEGER DEFAULT 0,
    followupLastError TEXT,
    emailStatus TEXT DEFAULT "skipped",
    emailSender TEXT,
    gmailMessageUrl TEXT,
    mailjetMessageId TEXT,
    mailjetMessageUuid TEXT,
    payload TEXT,
    createdAt TEXT,
    updatedAt TEXT
);

CREATE TABLE seminar_inquiries (
    id TEXT PRIMARY KEY,
    consultaId TEXT UNIQUE,
    seminarWpId INTEGER,
    seminarName TEXT,
    seminarType TEXT,
    firstName TEXT,
    lastName TEXT,
    fullName TEXT,
    email TEXT,
    emailNormalized TEXT,
    country TEXT,
    profession TEXT,
    educationLevel TEXT,
    source TEXT DEFAULT "Seminario",
    campaignProvider TEXT,
    campaignSource TEXT,
    campaignMedium TEXT,
    campaignName TEXT,
    campaignExternalId TEXT,
    campaignContent TEXT,
    campaignTerm TEXT,
    urlBase TEXT,
    urlReferer TEXT,
    inquiryAt TEXT,
    ipAddress TEXT,
    userAgent TEXT,
    replyToEmail TEXT,
    programUrl TEXT,
    cartaUrl TEXT,
    preinscripcionUrl TEXT,
    offerStatus TEXT,
    emailStatus TEXT DEFAULT "skipped",
    emailSender TEXT,
    gmailMessageUrl TEXT,
    mailjetMessageId TEXT,
    mailjetMessageUuid TEXT,
    payload TEXT,
    createdAt TEXT,
    updatedAt TEXT
);
');

FLACSO_DB::set_connection($pdo);
FLACSO_Base_Inquiry_Repository::clear_cache();

// 1. Probar offer_inquiries
$offer_repo = new FLACSO_Offer_Inquiry_Repository();
$res1 = $offer_repo->insert([
    'consultaId' => 'cid-offer-001',
    'offerWpId'  => 101,
    'offerName'  => 'Maestría de Prueba',
    'firstName'  => 'Laura',
    'lastName'   => 'Gómez',
    'fullName'   => 'Laura Gómez',
    'email'      => 'laura@ejemplo.com',
    'country'    => 'Uruguay',
]);

repo_assert(!empty($res1['id']), 'Debe retornar ID generado');
repo_assert(preg_match('/^c[0-9a-z]{24}$/', $res1['id']) === 1, 'ID generado debe cumplir con formato CUID (c + 24 alfanuméricos)');
repo_assert($res1['duplicate'] === false, 'No debe ser duplicado');
repo_assert($res1['consultaId'] === 'cid-offer-001', 'consultaId debe coincidir');

$found_offer_initial = $offer_repo->find_by_consulta_id('cid-offer-001');
repo_assert($found_offer_initial !== null, 'find_by_consulta_id debe encontrar el registro insertado');
repo_assert($found_offer_initial['offerName'] === 'Maestría de Prueba', 'offerName debe coincidir');
repo_assert($found_offer_initial['emailNormalized'] === 'laura@ejemplo.com', 'emailNormalized debe ser normalizado a minúsculas');
repo_assert($found_offer_initial['emailStatus'] === 'skipped', 'emailStatus inicial debe ser skipped');
repo_assert(!empty($found_offer_initial['inquiryAt']), 'inquiryAt debe ser asignado automáticamente');

// Idempotencia en offer_inquiries
$res1_dup = $offer_repo->insert([
    'consultaId' => 'cid-offer-001',
    'offerWpId'  => 101,
    'offerName'  => 'Maestría de Prueba',
    'email'      => 'laura@ejemplo.com',
]);
repo_assert($res1_dup['duplicate'] === true, 'Debe detectar duplicado');
repo_assert($res1_dup['id'] === $res1['id'], 'El ID debe ser idéntico al original');

// Actualizar emailStatus con sender, message_id y message_uuid
$offer_repo->update_email_status('cid-offer-001', 'sent', 'remitente@flacso.edu.uy', 'mj-12345', 'uuid-mj-offer-001');
$found_offer = $offer_repo->find_by_consulta_id('cid-offer-001');
repo_assert($found_offer['emailStatus'] === 'sent', 'emailStatus debe ser sent');
repo_assert($found_offer['emailSender'] === 'remitente@flacso.edu.uy', 'emailSender debe ser actualizado');
repo_assert($found_offer['mailjetMessageId'] === 'mj-12345', 'mailjetMessageId debe ser mj-12345');
repo_assert($found_offer['mailjetMessageUuid'] === 'uuid-mj-offer-001', 'mailjetMessageUuid debe ser uuid-mj-offer-001');
repo_assert(!empty($found_offer['updatedAt']), 'updatedAt debe estar actualizado');

// La reserva de reenvío debe ser atómica: una segunda solicitud no puede
// obtener el mismo registro una vez que la primera lo puso en processing.
$offer_repo->update_email_status('cid-offer-001', 'failed');
repo_assert(
    method_exists($offer_repo, 'claim_failed_email_retry'),
    'El repositorio debe reservar atómicamente un reenvío fallido'
);
repo_assert(
    $offer_repo->claim_failed_email_retry('cid-offer-001') === true,
    'La primera reserva de un correo fallido debe obtener el envío'
);
repo_assert(
    $offer_repo->claim_failed_email_retry('cid-offer-001') === false,
    'Una segunda reserva concurrente no debe poder reenviar el mismo correo'
);
$claimed_offer = $offer_repo->find_by_consulta_id('cid-offer-001');
repo_assert($claimed_offer['emailStatus'] === 'processing', 'La reserva debe marcar el correo como processing antes de llamar a Mailjet');

// 2. Probar guarda contra strings vacíos en TIMESTAMP (inquiryAt, createdAt, updatedAt)
$res_empty_ts = $offer_repo->insert([
    'consultaId' => 'cid-offer-empty-ts',
    'offerName'  => 'Diploma en Políticas',
    'email'      => 'diego@ejemplo.com',
    'inquiryAt'  => '', // String vacío debe ser reemplazado por timestamp actual
    'createdAt'  => '   ',
    'updatedAt'  => '',
]);
repo_assert($res_empty_ts['duplicate'] === false, 'Debe insertar con campos de fecha vacíos');
$found_empty_ts = $offer_repo->find_by_consulta_id('cid-offer-empty-ts');
repo_assert(!empty($found_empty_ts['inquiryAt']) && trim($found_empty_ts['inquiryAt']) !== '', 'inquiryAt no debe ser string vacío');
repo_assert(!empty($found_empty_ts['createdAt']) && trim($found_empty_ts['createdAt']) !== '', 'createdAt no debe ser string vacío');
repo_assert(!empty($found_empty_ts['updatedAt']) && trim($found_empty_ts['updatedAt']) !== '', 'updatedAt no debe ser string vacío');

// 3. Probar seminar_inquiries
$seminar_repo = new FLACSO_Seminar_Inquiry_Repository();
$res2 = $seminar_repo->insert([
    'consultaId'   => 'cid-sem-001',
    'seminarWpId'  => 202,
    'seminarName'  => 'Seminario Género',
    'firstName'    => 'Carlos',
    'lastName'     => 'Ruiz',
    'fullName'     => 'Carlos Ruiz',
    'email'        => 'carlos@ejemplo.com',
    'inquiryAt'    => '', // Verificación de guarda de timestamp en seminarios
]);
repo_assert(!empty($res2['id']), 'Debe retornar ID de seminario');
repo_assert(preg_match('/^c[0-9a-z]{24}$/', $res2['id']) === 1, 'ID de seminario debe tener formato CUID');
repo_assert($res2['duplicate'] === false, 'Seminario no debe ser duplicado');

$found_sem = $seminar_repo->find_by_consulta_id('cid-sem-001');
repo_assert(!empty($found_sem['inquiryAt']) && trim($found_sem['inquiryAt']) !== '', 'inquiryAt de seminario no debe ser string vacío');

// Idempotencia en seminar_inquiries
$res2_dup = $seminar_repo->insert([
    'consultaId'   => 'cid-sem-001',
    'seminarName'  => 'Seminario Género',
    'email'        => 'carlos@ejemplo.com',
]);
repo_assert($res2_dup['duplicate'] === true, 'Seminario duplicado debe reportar duplicate true');
repo_assert($res2_dup['id'] === $res2['id'], 'ID de seminario duplicado debe ser igual');

$seminar_repo->update_email_status('cid-sem-001', 'failed');
$found_sem_failed = $seminar_repo->find_by_consulta_id('cid-sem-001');
repo_assert($found_sem_failed['emailStatus'] === 'failed', 'emailStatus de seminario debe ser failed');

// 4. Probar búsqueda de consultaId inexistente
repo_assert($offer_repo->find_by_consulta_id('inexistente') === null, 'Consulta inexistente debe retornar null');
repo_assert($seminar_repo->find_by_consulta_id('inexistente') === null, 'Seminario inexistente debe retornar null');

// 5. Probar recuperación ante condición de carrera (concurrency race condition)
class FLACSO_Offer_Inquiry_Repository_Race_Test extends FLACSO_Offer_Inquiry_Repository {
    private int $lookup_count = 0;
    public function find_by_consulta_id(string $consulta_id): ?array {
        $this->lookup_count++;
        if ($this->lookup_count === 1) {
            return null; // Simula que la consulta concurrente aún no era visible en el chequeo previo
        }
        return parent::find_by_consulta_id($consulta_id);
    }
}

$race_repo = new FLACSO_Offer_Inquiry_Repository_Race_Test();
$race_res = $race_repo->insert([
    'consultaId' => 'cid-offer-001',
    'offerWpId'  => 101,
    'offerName'  => 'Maestría de Prueba',
    'email'      => 'laura@ejemplo.com',
]);
repo_assert($race_res['duplicate'] === true, 'Debe capturar colisión de unicidad y recuperar duplicate true');
repo_assert($race_res['id'] === $res1['id'], 'El ID recuperado tras colisión debe coincidir con el original');

// 6. Probar campos de snapshot de cohorte, mautic y método find_pending_by_email_and_cohort
repo_assert(method_exists($offer_repo, 'find_pending_by_email_and_cohort'), 'Método find_pending_by_email_and_cohort debe existir en FLACSO_Offer_Inquiry_Repository');

$repo = new FLACSO_Offer_Inquiry_Repository();
$res = $repo->insert([
    'consultaId'          => 'c-snapshot-01',
    'offerWpId'           => 417,
    'offerName'           => 'DAVIA',
    'offerAbbreviation'   => 'davia',
    'cohortWpId'          => 813,
    'cohortNumber'        => 10,
    'cohortName'          => 'Cohorte X',
    'registrationOpenAt'  => '2026-09-01T00:00:00Z',
    'registrationCloseAt' => '2026-09-30T23:59:59Z',
    'offerStatus'         => 'abierta',
    'firstName'           => 'Ana',
    'lastName'            => 'Pérez',
    'email'               => 'ana@example.com',
]);

repo_assert(!empty($res['id']), 'Debe retornar un ID de inserción');
$found = $repo->find_by_consulta_id('c-snapshot-01');
repo_assert(isset($found['offerAbbreviation']) && $found['offerAbbreviation'] === 'davia', 'offerAbbreviation debe ser davia');
repo_assert(isset($found['cohortWpId']) && (int)$found['cohortWpId'] === 813, 'cohortWpId debe ser 813');
repo_assert(isset($found['cohortNumber']) && (int)$found['cohortNumber'] === 10, 'cohortNumber debe ser 10');
repo_assert(isset($found['cohortName']) && $found['cohortName'] === 'Cohorte X', 'cohortName debe ser Cohorte X');
repo_assert(isset($found['offerStatus']) && $found['offerStatus'] === 'abierta', 'offerStatus debe ser abierta');
repo_assert(isset($found['registrationOpenAt']) && $found['registrationOpenAt'] === '2026-09-01T00:00:00Z', 'registrationOpenAt coincide');
repo_assert(isset($found['registrationCloseAt']) && $found['registrationCloseAt'] === '2026-09-30T23:59:59Z', 'registrationCloseAt coincide');

// Probar búsqueda de consulta pendiente por email y cohorte
repo_assert($repo->find_pending_by_email_and_cohort('ana@example.com', 813) === null, 'No debe retornar consulta si followupStatus es none');

$res_pending = $repo->insert([
    'consultaId'     => 'c-pending-01',
    'cohortWpId'     => 813,
    'email'          => 'ana@example.com',
    'followupStatus' => 'pending',
]);
$found_pending = $repo->find_pending_by_email_and_cohort('ana@example.com', 813);
repo_assert($found_pending !== null, 'Debe encontrar consulta pendiente por email y cohorte');
repo_assert($found_pending['consultaId'] === 'c-pending-01', 'consultaId de pendiente coincide');

// Boundary assertions para find_pending_by_email_and_cohort
repo_assert($repo->find_pending_by_email_and_cohort('', 813) === null, 'Email vacío debe retornar null');
repo_assert($repo->find_pending_by_email_and_cohort('   ', 813) === null, 'Email con solo espacios debe retornar null');
repo_assert($repo->find_pending_by_email_and_cohort('ana@example.com', 0) === null, 'cohort_id = 0 debe retornar null');
repo_assert($repo->find_pending_by_email_and_cohort('ana@example.com', -1) === null, 'cohort_id < 0 debe retornar null');

// Variación con mayúsculas: normaliza y encuentra el registro
$found_uppercase = $repo->find_pending_by_email_and_cohort('ANA@EXAMPLE.COM', 813);
repo_assert($found_uppercase !== null, 'Email en mayúsculas debe normalizarse y encontrar el registro');
repo_assert($found_uppercase['consultaId'] === 'c-pending-01', 'consultaId coincide para email en mayúsculas');

// 7. Probar método update_mautic_status
function test_update_mautic_status(FLACSO_Offer_Inquiry_Repository $repo): void {
    // Inserta una consulta académica
    $insert_res = $repo->insert([
        'consultaId'        => 'c-mautic-test-01',
        'offerWpId'         => 500,
        'offerName'         => 'Programa Mautic Test',
        'offerAbbreviation' => 'pmt',
        'firstName'         => 'Lucía',
        'lastName'          => 'Méndez',
        'email'             => 'lucia.mendez@example.com',
    ]);

    repo_assert(!empty($insert_res['id']), 'Debe retornar ID al insertar consulta');
    $id = $insert_res['id'];

    // Llama a update_mautic_status con datos exitosos
    $synced_at = '2026-09-27 15:00:00';
    $ok1 = $repo->update_mautic_status($id, [
        'mauticContactId'  => 1234,
        'mauticSyncStatus' => 'synced',
        'mauticSyncedAt'   => $synced_at,
        'mauticLastError'  => null,
    ]);
    repo_assert($ok1 === true, 'update_mautic_status debe retornar true en éxito');

    $found1 = $repo->find_by_id($id);
    repo_assert($found1 !== null, 'find_by_id debe encontrar la consulta');
    repo_assert((int)$found1['mauticContactId'] === 1234, 'mauticContactId debe ser 1234');
    repo_assert($found1['mauticSyncStatus'] === 'synced', 'mauticSyncStatus debe ser synced');
    repo_assert($found1['mauticSyncedAt'] === $synced_at, 'mauticSyncedAt debe coincidir con la fecha provista');
    repo_assert($found1['mauticLastError'] === null, 'mauticLastError debe ser null');
    repo_assert(!empty($found1['updatedAt']), 'updatedAt debe estar actualizado');

    // Llama a update_mautic_status para registrar una falla
    $error_msg = 'Connection timed out after 4 seconds';
    $ok2 = $repo->update_mautic_status($id, [
        'mauticSyncStatus' => 'failed',
        'mauticLastError'  => $error_msg,
    ]);
    repo_assert($ok2 === true, 'update_mautic_status debe retornar true al actualizar a failed');

    $found2 = $repo->find_by_id($id);
    repo_assert($found2 !== null, 'find_by_id debe encontrar la consulta tras falla');
    repo_assert((int)$found2['mauticContactId'] === 1234, 'mauticContactId debe preservar el valor previo');
    repo_assert($found2['mauticSyncStatus'] === 'failed', 'mauticSyncStatus debe ser failed');
    repo_assert($found2['mauticLastError'] === $error_msg, 'mauticLastError debe ser el mensaje de error');
    repo_assert($found2['mauticSyncedAt'] === $synced_at, 'mauticSyncedAt debe preservar el valor previo');

    // Comportamiento defensivo: ID inexistente
    repo_assert($repo->update_mautic_status('c-inexistente-uuid-999', ['mauticSyncStatus' => 'synced']) === false, 'ID inexistente debe retornar false');

    // Comportamiento defensivo: ID vacío o espacios
    repo_assert($repo->update_mautic_status('', ['mauticSyncStatus' => 'synced']) === false, 'ID vacío debe retornar false');
    repo_assert($repo->update_mautic_status('   ', ['mauticSyncStatus' => 'synced']) === false, 'ID con solo espacios debe retornar false');

    // Comportamiento defensivo: datos vacíos o sin campos válidos
    repo_assert($repo->update_mautic_status($id, []) === false, 'Array de datos vacío debe retornar false');
    repo_assert($repo->update_mautic_status($id, ['campoInvalido' => 'valor']) === false, 'Array sin campos válidos debe retornar false');
    repo_assert($repo->update_mautic_status($id, ['mauticSyncStatus' => 'estado_invalido']) === false, 'Estado Mautic no permitido debe retornar false');

    // Estados adicionales permitidos: skipped, pending y reset de contactId
    $ok_skipped = $repo->update_mautic_status($id, ['mauticSyncStatus' => 'skipped', 'mauticContactId' => null]);
    repo_assert($ok_skipped === true, 'update_mautic_status debe permitir estado skipped y contactId null');
    $found_skipped = $repo->find_by_id($id);
    repo_assert($found_skipped['mauticSyncStatus'] === 'skipped', 'mauticSyncStatus debe ser skipped');
    repo_assert($found_skipped['mauticContactId'] === null, 'mauticContactId debe ser null tras reset');

    $ok_pending = $repo->update_mautic_status($id, ['mauticSyncStatus' => 'pending']);
    repo_assert($ok_pending === true, 'update_mautic_status debe permitir estado pending');
    $found_pending = $repo->find_by_id($id);
    repo_assert($found_pending['mauticSyncStatus'] === 'pending', 'mauticSyncStatus debe ser pending');

    // Edge cases de find_by_id
    repo_assert($repo->find_by_id('') === null, 'find_by_id con string vacío debe retornar null');
    repo_assert($repo->find_by_id('   ') === null, 'find_by_id con espacios debe retornar null');
    repo_assert($repo->find_by_id('inexistente-id-999') === null, 'find_by_id inexistente debe retornar null');
}

test_update_mautic_status($repo);

// 8. Probar métodos de seguimiento: claim_due_followups, update_followup_status, has_newer_inquiry_for_offer
function test_followup_methods(FLACSO_Offer_Inquiry_Repository $repo): void {
    $now_ts = time();
    $past_due_a = gmdate('Y-m-d H:i:s', $now_ts - 7200);   // Hace 2 horas
    $future_due_b = gmdate('Y-m-d H:i:s', $now_ts + 172800); // En 2 días
    $past_due_c = gmdate('Y-m-d H:i:s', $now_ts - 3600);   // Hace 1 hora
    $past_due_d = gmdate('Y-m-d H:i:s', $now_ts - 10800);  // Hace 3 horas

    // Consulta A: followupStatus = 'pending', followupDueAt en el pasado, attempts = 0
    $ins_a = $repo->insert([
        'consultaId'       => 'c-followup-a',
        'offerWpId'        => 601,
        'offerName'        => 'Oferta Followup A',
        'email'            => 'aspirante.a@example.com',
        'inquiryAt'        => '2026-09-20 10:00:00',
        'followupStatus'   => 'pending',
        'followupDueAt'    => $past_due_a,
        'followupAttempts' => 0,
    ]);
    repo_assert(!empty($ins_a['id']), 'Debe insertar Consulta A');
    $id_a = $ins_a['id'];

    // Consulta B: followupStatus = 'pending', followupDueAt en el futuro, attempts = 0
    $ins_b = $repo->insert([
        'consultaId'       => 'c-followup-b',
        'offerWpId'        => 601,
        'offerName'        => 'Oferta Followup B',
        'email'            => 'aspirante.b@example.com',
        'inquiryAt'        => '2026-09-27 10:00:00',
        'followupStatus'   => 'pending',
        'followupDueAt'    => $future_due_b,
        'followupAttempts' => 0,
    ]);
    repo_assert(!empty($ins_b['id']), 'Debe insertar Consulta B');
    $id_b = $ins_b['id'];

    // Consulta C: followupStatus = 'pending', followupDueAt en el pasado, attempts = 3
    $ins_c = $repo->insert([
        'consultaId'       => 'c-followup-c',
        'offerWpId'        => 602,
        'offerName'        => 'Oferta Followup C',
        'email'            => 'aspirante.c@example.com',
        'inquiryAt'        => '2026-09-20 12:00:00',
        'followupStatus'   => 'pending',
        'followupDueAt'    => $past_due_c,
        'followupAttempts' => 3,
    ]);
    repo_assert(!empty($ins_c['id']), 'Debe insertar Consulta C');
    $id_c = $ins_c['id'];

    // Consulta D: followupStatus = 'sent', followupDueAt en el pasado
    $ins_d = $repo->insert([
        'consultaId'       => 'c-followup-d',
        'offerWpId'        => 603,
        'offerName'        => 'Oferta Followup D',
        'email'            => 'aspirante.d@example.com',
        'inquiryAt'        => '2026-09-18 09:00:00',
        'followupStatus'   => 'sent',
        'followupDueAt'    => $past_due_d,
        'followupAttempts' => 1,
    ]);
    repo_assert(!empty($ins_d['id']), 'Debe insertar Consulta D');
    $id_d = $ins_d['id'];

    // 8.1 Ejecutar claim_due_followups(): sólo debe reclamar Consulta A
    $claimed = $repo->claim_due_followups();
    repo_assert(count($claimed) === 1, 'claim_due_followups debe reclamar exactamente 1 consulta');
    repo_assert($claimed[0]['id'] === $id_a, 'La consulta reclamada debe ser la Consulta A');
    repo_assert($claimed[0]['followupStatus'] === 'processing', 'El estado devuelto en el array debe ser processing');

    // Verificar en BD que Consulta A pasó a processing
    $db_a = $repo->find_by_id($id_a);
    repo_assert($db_a !== null, 'Consulta A debe existir en BD');
    repo_assert($db_a['followupStatus'] === 'processing', 'Consulta A en BD debe estar en estado processing');

    // 8.2 Ejecutar claim_due_followups() nuevamente: debe devolver [] (Consulta A ya está en processing)
    $claimed_again = $repo->claim_due_followups();
    repo_assert(empty($claimed_again), 'Segunda llamada a claim_due_followups debe retornar array vacío');

    // 8.3 Probar update_followup_status() actualizando Consulta A a 'sent'
    $ok_update = $repo->update_followup_status($id_a, 'sent');
    repo_assert($ok_update === true, 'update_followup_status debe retornar true al actualizar a sent');

    $db_a_sent = $repo->find_by_id($id_a);
    repo_assert($db_a_sent !== null, 'Consulta A debe encontrarse tras update');
    repo_assert($db_a_sent['followupStatus'] === 'sent', 'followupStatus debe ser sent');
    repo_assert((int)$db_a_sent['followupAttempts'] === 1, 'followupAttempts debe haberse incrementado a 1');
    repo_assert(!empty($db_a_sent['followupSentAt']), 'followupSentAt debe haberse poblado automáticamente con gmdate(c)');
    repo_assert($db_a_sent['followupLastError'] === null, 'followupLastError debe ser null');

    // Probar update_followup_status a 'failed' con error
    $ok_fail = $repo->update_followup_status($id_b, 'failed', 'Error timeout con Mautic API');
    repo_assert($ok_fail === true, 'update_followup_status debe retornar true al actualizar a failed');
    $db_b_fail = $repo->find_by_id($id_b);
    repo_assert($db_b_fail['followupStatus'] === 'failed', 'followupStatus debe ser failed');
    repo_assert((int)$db_b_fail['followupAttempts'] === 1, 'followupAttempts debe ser 1');
    repo_assert($db_b_fail['followupLastError'] === 'Error timeout con Mautic API', 'followupLastError debe coincidir');

    // Probar update_followup_status a 'skipped'
    $ok_skip = $repo->update_followup_status($id_c, 'skipped', 'Existe consulta más reciente');
    repo_assert($ok_skip === true, 'update_followup_status debe retornar true al actualizar a skipped');
    $db_c_skip = $repo->find_by_id($id_c);
    repo_assert($db_c_skip['followupStatus'] === 'skipped', 'followupStatus debe ser skipped');
    repo_assert((int)$db_c_skip['followupAttempts'] === 4, 'followupAttempts debe incrementarse de 3 a 4');
    repo_assert($db_c_skip['followupLastError'] === 'Existe consulta más reciente', 'followupLastError debe coincidir');

    // Defensivos de update_followup_status
    repo_assert($repo->update_followup_status('', 'sent') === false, 'ID vacío debe retornar false');
    repo_assert($repo->update_followup_status('   ', 'sent') === false, 'ID con solo espacios debe retornar false');
    repo_assert($repo->update_followup_status($id_a, 'estado_invalido') === false, 'Estado inválido debe retornar false');
    repo_assert($repo->update_followup_status('id-inexistente-12345', 'sent') === false, 'ID inexistente debe retornar false');

    // 8.4 Probar has_newer_inquiry_for_offer()
    // Caso 1: Insertar una consulta posterior para aspirante.a@example.com y oferta 601
    // Consulta original A tiene inquiryAt = '2026-09-20 10:00:00'
    $has_newer_before = $repo->has_newer_inquiry_for_offer('aspirante.a@example.com', 601, '2026-09-20 10:00:00');
    repo_assert($has_newer_before === false, 'has_newer_inquiry_for_offer debe ser false antes de registrar consulta posterior');

    // Insertar consulta posterior (2 días después)
    $ins_newer = $repo->insert([
        'consultaId' => 'c-followup-newer',
        'offerWpId'  => 601,
        'offerName'  => 'Oferta Followup A',
        'email'      => 'aspirante.a@example.com',
        'inquiryAt'  => '2026-09-22 15:00:00',
    ]);
    repo_assert(!empty($ins_newer['id']), 'Debe insertar consulta posterior');

    $has_newer_after = $repo->has_newer_inquiry_for_offer('aspirante.a@example.com', 601, '2026-09-20 10:00:00');
    repo_assert($has_newer_after === true, 'has_newer_inquiry_for_offer debe ser true tras registrar consulta posterior');

    // Normalización de email (mayúsculas con espacios)
    $has_newer_upper = $repo->has_newer_inquiry_for_offer('  ASPIRANTE.A@EXAMPLE.COM  ', 601, '2026-09-20 10:00:00');
    repo_assert($has_newer_upper === true, 'has_newer_inquiry_for_offer debe normalizar mayúsculas y espacios');

    // Misma fecha o fecha posterior a la segunda consulta -> debe ser false
    $has_newer_from_second = $repo->has_newer_inquiry_for_offer('aspirante.a@example.com', 601, '2026-09-22 15:00:00');
    repo_assert($has_newer_from_second === false, 'has_newer_inquiry_for_offer con la fecha más reciente debe retornar false');

    // Otra oferta no debe interferir
    $has_newer_other_offer = $repo->has_newer_inquiry_for_offer('aspirante.a@example.com', 999, '2026-09-20 10:00:00');
    repo_assert($has_newer_other_offer === false, 'has_newer_inquiry_for_offer con otra oferta debe retornar false');

    // Boundary checks de has_newer_inquiry_for_offer
    repo_assert($repo->has_newer_inquiry_for_offer('', 601, '2026-09-20 10:00:00') === false, 'Email vacío debe retornar false');
    repo_assert($repo->has_newer_inquiry_for_offer('   ', 601, '2026-09-20 10:00:00') === false, 'Email con espacios debe retornar false');
    repo_assert($repo->has_newer_inquiry_for_offer('aspirante.a@example.com', 0, '2026-09-20 10:00:00') === false, 'offer_wp_id = 0 debe retornar false');
    repo_assert($repo->has_newer_inquiry_for_offer('aspirante.a@example.com', -5, '2026-09-20 10:00:00') === false, 'offer_wp_id negativo debe retornar false');
    repo_assert($repo->has_newer_inquiry_for_offer('aspirante.a@example.com', 601, '') === false, 'current_inquiry_at vacío debe retornar false');
    repo_assert($repo->has_newer_inquiry_for_offer('aspirante.a@example.com', 601, '   ') === false, 'current_inquiry_at con solo espacios debe retornar false');

    // 8.5 Test update_followup_status con fecha explícita y otros estados válidos ('processing', 'none')
    $custom_sent_at = '2026-09-28 14:30:00';
    $ok_custom = $repo->update_followup_status($id_d, 'sent', 'Enviado por Mailjet', $custom_sent_at);
    repo_assert($ok_custom === true, 'update_followup_status debe aceptar sent_at personalizado');
    $db_d_custom = $repo->find_by_id($id_d);
    repo_assert($db_d_custom['followupSentAt'] === $custom_sent_at, 'followupSentAt debe coincidir con el personalizado');
    repo_assert($db_d_custom['followupLastError'] === 'Enviado por Mailjet', 'followupLastError debe coincidir');

    $ok_none = $repo->update_followup_status($id_d, 'none');
    repo_assert($ok_none === true, 'update_followup_status debe permitir estado none');
    $db_d_none = $repo->find_by_id($id_d);
    repo_assert($db_d_none['followupStatus'] === 'none', 'followupStatus debe ser none');

    // 8.6 Test límite en claim_due_followups: insertar 3 registros vencidos y pedir limit = 2
    for ($i = 1; $i <= 3; $i++) {
        $repo->insert([
            'consultaId'       => "c-limit-{$i}",
            'offerWpId'        => 700,
            'offerName'        => 'Oferta Límite',
            'email'            => "limit{$i}@example.com",
            'followupStatus'   => 'pending',
            'followupDueAt'    => gmdate('Y-m-d H:i:s', $now_ts - 5000 + ($i * 10)),
            'followupAttempts' => 0,
        ]);
    }
    $claimed_limited = $repo->claim_due_followups(2);
    repo_assert(count($claimed_limited) === 2, 'claim_due_followups(2) debe retornar exactamente 2 registros');
    // El tercer registro restante todavía debe poder ser reclamado
    $claimed_remaining = $repo->claim_due_followups(10);
    repo_assert(count($claimed_remaining) === 1, 'claim_due_followups siguiente debe retornar el registro restante');
    repo_assert($claimed_remaining[0]['consultaId'] === 'c-limit-3', 'El restante debe ser c-limit-3');
}

test_followup_methods($repo);

echo "OK inquiry-repositories-test\n";
