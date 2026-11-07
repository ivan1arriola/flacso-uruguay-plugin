<?php
/**
 * Repositorio de snapshots y entregas transaccionales de consultas.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_DB')) {
    require_once dirname(__DIR__) . '/class-flacso-db.php';
}
if (!class_exists('FLACSO_Base_Inquiry_Repository')) {
    require_once __DIR__ . '/class-flacso-base-inquiry-repository.php';
}
if (!class_exists('FLACSO_Inquiry_Snapshot')) {
    require_once dirname(__DIR__, 3) . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
}

/** La reserva de una entrega venció o la tomó otro proceso. */
class FLACSO_Delivery_Claim_Lost_Exception extends RuntimeException {}

final class FLACSO_Inquiry_Delivery_Repository {
    /** Reclamos máximos por entrega: un reclamo equivale a una pasada del worker. */
    public const MAX_CLAIM_ATTEMPTS = 6;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? FLACSO_DB::connection();
    }

    /**
     * Inserta consulta, snapshot y entrega inicial dentro de una única transacción.
     */
    public function persist_inquiry_with_delivery(
        FLACSO_Base_Inquiry_Repository $repository,
        array $record,
        array $snapshot,
        string $type,
        string $provider = '',
        array $consent = []
    ): array {
        FLACSO_Inquiry_Snapshot::assert_snapshot($snapshot);

        $type = strtolower(trim($type));
        if (!in_array($type, ['offer', 'seminar'], true)) {
            throw new InvalidArgumentException('Tipo de consulta no soportado.');
        }

        if ($provider === '') {
            $provider = class_exists('FLACSO_Mailing_Client')
                ? FLACSO_Mailing_Client::delivery_provider()
                : (function_exists('get_option') ? (string) get_option('flacso_inquiry_delivery_provider', 'mautic') : 'mautic');
        }
        $provider = strtolower(trim($provider));
        if (!in_array($provider, ['mautic', 'flacso_mail'], true)) {
            throw new InvalidArgumentException('Proveedor de entrega no soportado.');
        }

        $consulta_id = trim((string) ($snapshot['consultaId'] ?? ''));
        if ($consulta_id === '') {
            throw new InvalidArgumentException('consultaId es obligatorio.');
        }

        $owns_transaction = !$this->pdo->inTransaction();
        if ($owns_transaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $insert = $repository->insert($record);
            $inquiry_id = (string) ($insert['id'] ?? '');

            if (!empty($insert['duplicate'])) {
                $existing = $this->find_by_consulta_id($consulta_id);
                if ($owns_transaction) {
                    $this->pdo->commit();
                }
                return [
                    'id'          => $inquiry_id,
                    'consultaId'  => $consulta_id,
                    'duplicate'   => true,
                    'snapshot_id' => $existing['snapshotId'] ?? null,
                    'delivery_id' => $existing['id'] ?? null,
                    'state'       => $existing['state'] ?? null,
                ];
            }

            if ($inquiry_id === '') {
                throw new RuntimeException('No se obtuvo el ID persistido de la consulta.');
            }

            $snapshot_id = FLACSO_Base_Inquiry_Repository::generate_cuid();
            $delivery_id = FLACSO_Base_Inquiry_Repository::generate_cuid();
            $now = gmdate('c');
            $snapshot_json = self::json($snapshot);
            $payload_json = self::json([
                'tokens' => FLACSO_Inquiry_Snapshot::delivery_tokens($snapshot),
                'marketingConsent' => [
                    'granted' => !empty($consent['granted']),
                    'acceptedAt' => trim((string) ($consent['acceptedAt'] ?? '')),
                    'source' => trim((string) ($consent['source'] ?? '')),
                    'textVersion' => trim((string) ($consent['textVersion'] ?? '')),
                ],
            ]);
            $template = $this->template_identity($snapshot);
            $report_month = gmdate('Y-m-01', strtotime((string) ($snapshot['inquiryAt'] ?? $now)) ?: time());

            $stmt = $this->pdo->prepare(
                'INSERT INTO inquiry_snapshots
                    (id, "inquiryType", "inquiryId", "consultaId", "schemaVersion", "snapshotJson", "createdAt")
                 VALUES
                    (:id, :type, :inquiry_id, :consulta_id, :schema_version, :snapshot_json, :created_at)'
            );
            $stmt->execute([
                ':id'             => $snapshot_id,
                ':type'           => $type,
                ':inquiry_id'     => $inquiry_id,
                ':consulta_id'    => $consulta_id,
                ':schema_version' => (string) $snapshot['schemaVersion'],
                ':snapshot_json'  => $snapshot_json,
                ':created_at'     => $now,
            ]);

            $stmt = $this->pdo->prepare(
                'INSERT INTO inquiry_deliveries
                    (id, "snapshotId", "inquiryType", "inquiryId", "consultaId", "deliveryType", "assignedProvider",
                     state, email, "templateId", "templateVersion", "templateSha256", "payloadJson",
                     attempts, "reportMonth", "createdAt", "updatedAt")
                 VALUES
                    (:id, :snapshot_id, :type, :inquiry_id, :consulta_id, :delivery_type, :assigned_provider,
                     :state, :email, :template_id, :template_version, :template_sha, :payload_json,
                     0, :report_month, :created_at, :updated_at)'
            );
            $stmt->execute([
                ':id'               => $delivery_id,
                ':snapshot_id'      => $snapshot_id,
                ':type'             => $type,
                ':inquiry_id'       => $inquiry_id,
                ':consulta_id'      => $consulta_id,
                ':delivery_type'    => 'acknowledgement',
                ':assigned_provider' => $provider,
                ':state'            => 'pending',
                ':email'            => strtolower(trim((string) ($snapshot['recipient']['email'] ?? ''))),
                ':template_id'      => $template['template_id'],
                ':template_version' => $template['functional_version'],
                ':template_sha'     => $template['content_sha256'],
                ':payload_json'     => $payload_json,
                ':report_month'     => $report_month,
                ':created_at'       => $now,
                ':updated_at'       => $now,
            ]);

            if ($owns_transaction) {
                $this->pdo->commit();
            }

            return [
                'id'          => $inquiry_id,
                'consultaId'  => $consulta_id,
                'duplicate'   => false,
                'snapshot_id' => $snapshot_id,
                'delivery_id' => $delivery_id,
                'state'       => 'pending',
            ];
        } catch (Throwable $e) {
            if ($owns_transaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function find_by_delivery_id(string $delivery_id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, s."snapshotJson", s."schemaVersion"
             FROM inquiry_deliveries d
             LEFT JOIN inquiry_snapshots s ON s.id = d."snapshotId"
             WHERE d.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => trim($delivery_id)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find_by_consulta_id(string $consulta_id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, s."snapshotJson", s."schemaVersion"
             FROM inquiry_deliveries d
             LEFT JOIN inquiry_snapshots s ON s.id = d."snapshotId"
             WHERE d."consultaId" = :consulta_id AND d."deliveryType" = :delivery_type
             ORDER BY d."createdAt" ASC
             LIMIT 1'
        );
        $stmt->execute([
            ':consulta_id' => trim($consulta_id),
            ':delivery_type' => 'acknowledgement',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Antes de reclamar trabajo convierte reservas vencidas en resultado incierto.
     * Luego reclama filas mediante UPDATE condicional, que es la garantía primaria
     * frente a trabajadores concurrentes.
     */
    public function claim_pending_batch(int $limit = 10, int $lease_seconds = 120): array {
        $limit = max(1, min(100, $limit));
        $lease_seconds = max(30, min(900, $lease_seconds));
        $now = gmdate('c');
        $until = gmdate('c', time() + $lease_seconds);

        // Reserva vencida sin ningún intento externo autorizado: nada salió hacia el
        // proveedor, así que vuelve a la cola. "attemptStartedAt" vive en la misma fila
        // que la reserva y la fija record_attempt_start() con un UPDATE condicional, de
        // modo que recuperación e inicio del envío se excluyen entre sí a nivel de fila.
        // El NOT EXISTS es un cinturón extra para filas de una versión anterior (o de un
        // worker antiguo aún activo), que registraban el intento sin esa marca: sólo
        // vuelven a la cola si tampoco hay ningún intento registrado.
        $unstarted = $this->pdo->prepare(
            'UPDATE inquiry_deliveries
             SET state = :pending, "claimedAt" = NULL, "claimedUntil" = NULL, "claimToken" = NULL,
                 "lastErrorClass" = :error_class, "updatedAt" = :now
             WHERE state = :processing AND "claimedUntil" IS NOT NULL AND "claimedUntil" < :now
               AND "attemptStartedAt" IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM inquiry_delivery_attempts a
                   WHERE a."deliveryId" = inquiry_deliveries.id
                     AND (inquiry_deliveries."claimedAt" IS NULL OR a."startedAt" >= inquiry_deliveries."claimedAt")
                     AND COALESCE(a."errorClass", \'\') <> \'campaign_unavailable\'
               )'
        );
        $unstarted->execute([
            ':pending'     => 'pending',
            ':processing'  => 'processing',
            ':error_class' => 'lease_expired_before_attempt',
            ':now'         => $now,
        ]);

        // Reserva vencida con un intento iniciado: el resultado externo es incierto.
        $stale = $this->pdo->prepare(
            'UPDATE inquiry_deliveries
             SET state = :unknown, "terminalAt" = COALESCE("terminalAt", :now),
                 "lastErrorClass" = :error_class, "updatedAt" = :now
             WHERE state = :processing AND "claimedUntil" IS NOT NULL AND "claimedUntil" < :now'
        );
        $stale->execute([
            ':unknown'     => 'acceptance_unknown',
            ':processing'  => 'processing',
            ':error_class' => 'lease_expired_after_claim',
            ':now'         => $now,
        ]);

        // Presupuesto agotado: ninguna rama puede reintentarse indefinidamente.
        $exhausted = $this->pdo->prepare(
            'SELECT id FROM inquiry_deliveries
             WHERE (state = :pending OR state = :retryable) AND attempts >= ' . self::MAX_CLAIM_ATTEMPTS
        );
        $exhausted->execute([':pending' => 'pending', ':retryable' => 'retryable_failed']);
        foreach ($exhausted->fetchAll(PDO::FETCH_COLUMN) as $exhausted_id) {
            $close = $this->pdo->prepare(
                'UPDATE inquiry_deliveries
                 SET state = :failed, "terminalAt" = COALESCE("terminalAt", :now),
                     "lastErrorClass" = :error_class, "updatedAt" = :now
                 WHERE id = :id AND (state = :pending OR state = :retryable)'
            );
            $close->execute([
                ':failed'      => 'failed',
                ':now'         => $now,
                ':error_class' => 'attempts_exhausted',
                ':id'          => (string) $exhausted_id,
                ':pending'     => 'pending',
                ':retryable'   => 'retryable_failed',
            ]);
            if ($close->rowCount() === 1) {
                $this->sync_source_email_status((string) $exhausted_id, 'failed');
            }
        }

        $select = $this->pdo->prepare(
            'SELECT id
             FROM inquiry_deliveries
             WHERE (state = :pending OR state = :retryable)
               AND attempts < ' . self::MAX_CLAIM_ATTEMPTS . '
               AND ("nextAttemptAt" IS NULL OR "nextAttemptAt" <= :now)
             ORDER BY "createdAt" ASC
             LIMIT ' . $limit
        );
        $select->execute([
            ':pending'   => 'pending',
            ':retryable' => 'retryable_failed',
            ':now'       => $now,
        ]);
        $ids = $select->fetchAll(PDO::FETCH_COLUMN);

        $claimed = [];
        foreach ($ids as $id) {
            $token = bin2hex(random_bytes(16));
            $update = $this->pdo->prepare(
                'UPDATE inquiry_deliveries
                 SET state = :processing, attempts = attempts + 1, "claimedAt" = :now, "claimedUntil" = :until,
                     "claimToken" = :claim_token, "attemptStartedAt" = NULL, "updatedAt" = :now
                 WHERE id = :id
                   AND (state = :pending OR state = :retryable)
                   AND attempts < ' . self::MAX_CLAIM_ATTEMPTS . '
                   AND ("nextAttemptAt" IS NULL OR "nextAttemptAt" <= :now)'
            );
            $update->execute([
                ':processing' => 'processing',
                ':pending'    => 'pending',
                ':retryable'  => 'retryable_failed',
                ':now'        => $now,
                ':until'      => $until,
                ':claim_token'=> $token,
                ':id'         => (string) $id,
            ]);
            if ($update->rowCount() === 1) {
                $this->sync_source_email_status((string) $id, 'processing');
                $row = $this->find_by_delivery_id((string) $id);
                if ($row !== null) {
                    $claimed[] = $row;
                }
            }
        }

        return $claimed;
    }

    /**
     * Recalcula la plantilla de una entrega fallida/bloqueada a partir de su
     * snapshot. Esto permite corregir entregas históricas creadas cuando sólo
     * existía una plantilla F1 fija.
     */
    public function refresh_template_identity(string $delivery_id): ?array {
        $delivery_id = trim($delivery_id);
        if ($delivery_id === '') {
            return null;
        }

        $delivery = $this->find_by_delivery_id($delivery_id);
        if ($delivery === null) {
            return null;
        }

        $state = strtolower(trim((string) ($delivery['state'] ?? '')));
        if (!in_array($state, ['failed', 'blocked'], true)) {
            return $delivery;
        }

        $snapshot = json_decode((string) ($delivery['snapshotJson'] ?? ''), true);
        if (!is_array($snapshot)) {
            return $delivery;
        }

        $template = $this->template_identity($snapshot);
        $template_id = isset($template['template_id']) ? (int) $template['template_id'] : 0;
        if ($template_id <= 0) {
            return $delivery;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE inquiry_deliveries
             SET "templateId" = :template_id,
                 "templateVersion" = :template_version,
                 "templateSha256" = :template_sha,
                 "updatedAt" = :now
             WHERE id = :id
               AND (state = :failed OR state = :blocked)'
        );
        $stmt->execute([
            ':template_id' => $template_id,
            ':template_version' => (string) ($template['functional_version'] ?? ''),
            ':template_sha' => (string) ($template['content_sha256'] ?? ''),
            ':now' => gmdate('c'),
            ':id' => $delivery_id,
            ':failed' => 'failed',
            ':blocked' => 'blocked',
        ]);

        return $this->find_by_delivery_id($delivery_id);
    }

    /**
     * Reserva manualmente una entrega terminal cuyo resultado fue comprobado
     * como no enviado. Nunca permite reintentar acceptance_unknown ni accepted.
     */
    public function claim_manual_retry(string $delivery_id, int $lease_seconds = 120): bool {
        $delivery_id = trim($delivery_id);
        if ($delivery_id === '') {
            return false;
        }

        $lease_seconds = max(30, min(900, $lease_seconds));
        $now = gmdate('c');
        $until = gmdate('c', time() + $lease_seconds);
        $token = bin2hex(random_bytes(16));

        $stmt = $this->pdo->prepare(
            'UPDATE inquiry_deliveries
             SET state = :processing,
                 attempts = 1,
                 "attemptStartedAt" = NULL,
                 "terminalAt" = NULL,
                 "acceptedAt" = NULL,
                 "nextAttemptAt" = NULL,
                 "claimedAt" = :now,
                 "claimedUntil" = :until,
                 "claimToken" = :claim_token,
                 "lastHttpCode" = NULL,
                 "lastErrorClass" = NULL,
                 "lastError" = NULL,
                 "updatedAt" = :now
             WHERE id = :id
               AND (state = :failed OR state = :blocked)'
        );
        $stmt->execute([
            ':processing'  => 'processing',
            ':failed'      => 'failed',
            ':blocked'     => 'blocked',
            ':now'         => $now,
            ':until'       => $until,
            ':claim_token' => $token,
            ':id'          => $delivery_id,
        ]);

        $claimed = $stmt->rowCount() === 1;
        if ($claimed) {
            $this->sync_source_email_status($delivery_id, 'processing');
        }

        return $claimed;
    }

    /**
     * Registra el inicio de un intento externo. Con $claim_token exige que la
     * reserva siga vigente y pertenezca a quien llama: si se perdió, lanza
     * FLACSO_Delivery_Claim_Lost_Exception y el llamador debe abandonar sin enviar.
     */
    public function record_attempt_start(string $delivery_id, string $attempt_id, ?string $claim_token = null): string {
        $now = gmdate('c');

        // Un solo UPDATE condicional valida propiedad y vigencia de la reserva y deja
        // constancia de que el envío está autorizado. Si la recuperación ganó la fila,
        // no afecta ninguna fila y el llamador abandona sin enviar.
        $authorize = $this->pdo->prepare(
            'UPDATE inquiry_deliveries
             SET "attemptStartedAt" = :now, "updatedAt" = :now
             WHERE id = :id AND state = :processing
               AND "claimedUntil" IS NOT NULL AND "claimedUntil" >= :now'
            . (($claim_token !== null && $claim_token !== '') ? ' AND "claimToken" = :token' : '')
        );
        $params = [':now' => $now, ':id' => $delivery_id, ':processing' => 'processing'];
        if ($claim_token !== null && $claim_token !== '') {
            $params[':token'] = $claim_token;
        }
        $authorize->execute($params);
        if ($authorize->rowCount() !== 1) {
            throw new FLACSO_Delivery_Claim_Lost_Exception('La reserva de la entrega ya no pertenece a este proceso.');
        }

        $attempt_row_id = FLACSO_Base_Inquiry_Repository::generate_cuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO inquiry_delivery_attempts
                (id, "deliveryId", "attemptId", state, "startedAt")
             VALUES (:id, :delivery_id, :attempt_id, :state, :started_at)'
        );
        $stmt->execute([
            ':id'          => $attempt_row_id,
            ':delivery_id' => $delivery_id,
            ':attempt_id'  => $attempt_id,
            ':state'       => 'started',
            ':started_at'  => $now,
        ]);

        return $attempt_row_id;
    }

    /**
     * Un rechazo determinista previo al envío (p. ej. la oferta no tiene campaña)
     * garantiza que nada salió: se retira la marca para que, si la reserva vence
     * antes del acuse de respaldo, la entrega vuelva a la cola y no quede incierta.
     */
    public function clear_attempt_started(string $delivery_id, ?string $claim_token = null): void {
        $token_sql = ($claim_token !== null && $claim_token !== '') ? ' AND "claimToken" = :token' : '';
        $stmt = $this->pdo->prepare(
            'UPDATE inquiry_deliveries SET "attemptStartedAt" = NULL, "updatedAt" = :now
             WHERE id = :id AND state = :processing' . $token_sql
        );
        $params = [':now' => gmdate('c'), ':id' => $delivery_id, ':processing' => 'processing'];
        if ($token_sql !== '') {
            $params[':token'] = $claim_token;
        }
        $stmt->execute($params);
    }

    public function finish_attempt(
        string $attempt_id,
        string $state,
        ?int $http_code = null,
        ?string $error_class = null,
        ?string $error_message = null
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE inquiry_delivery_attempts
             SET state = :state, "finishedAt" = :finished_at, "httpCode" = :http_code,
                 "errorClass" = :error_class, "errorMessage" = :error_message
             WHERE "attemptId" = :attempt_id'
        );
        $stmt->execute([
            ':state'         => $state,
            ':finished_at'   => gmdate('c'),
            ':http_code'     => $http_code,
            ':error_class'   => self::redact($error_class),
            ':error_message' => self::redact($error_message),
            ':attempt_id'    => $attempt_id,
        ]);
    }

    public function set_contact_id(string $delivery_id, int $contact_id, ?string $claim_token = null): void {
        $token_sql = ($claim_token !== null && $claim_token !== '') ? ' AND "claimToken" = :token' : '';
        $stmt = $this->pdo->prepare(
            'UPDATE inquiry_deliveries SET "contactId" = :contact_id, "updatedAt" = :now WHERE id = :id' . $token_sql
        );
        $params = [
            ':contact_id' => $contact_id > 0 ? $contact_id : null,
            ':now' => gmdate('c'),
            ':id' => $delivery_id,
        ];
        if ($token_sql !== '') {
            $params[':token'] = $claim_token;
        }
        $stmt->execute($params);
    }

    public function mark_accepted(string $delivery_id, ?int $http_code = null, ?string $claim_token = null): bool {
        return $this->transition_terminal($delivery_id, 'accepted', $http_code, null, null, true, $claim_token);
    }

    public function mark_acceptance_unknown(string $delivery_id, ?string $error_class = null, ?string $error = null, ?string $claim_token = null): bool {
        return $this->transition_terminal($delivery_id, 'acceptance_unknown', null, $error_class, $error, false, $claim_token);
    }

    public function mark_failed(string $delivery_id, ?int $http_code = null, ?string $error_class = null, ?string $error = null, ?string $claim_token = null): bool {
        return $this->transition_terminal($delivery_id, 'failed', $http_code, $error_class, $error, false, $claim_token);
    }

    public function mark_blocked(string $delivery_id, ?string $error_class = null, ?string $error = null, ?string $claim_token = null): bool {
        return $this->transition_terminal($delivery_id, 'blocked', null, $error_class, $error, false, $claim_token);
    }

    public function mark_retryable_failure(
        string $delivery_id,
        ?int $http_code,
        ?string $error_class,
        ?string $error,
        int $delay_seconds = 300,
        ?string $claim_token = null
    ): bool {
        $next = gmdate('c', time() + max(60, $delay_seconds));
        $token_sql = ($claim_token !== null && $claim_token !== '') ? ' AND "claimToken" = :token' : '';
        $stmt = $this->pdo->prepare(
            'UPDATE inquiry_deliveries
             SET state = :state, "nextAttemptAt" = :next_attempt, "claimedAt" = NULL,
                 "claimedUntil" = NULL, "claimToken" = NULL, "lastHttpCode" = :http_code,
                 "lastErrorClass" = :error_class, "lastError" = :error, "updatedAt" = :now
             WHERE id = :id AND state = :processing' . $token_sql
        );
        $params = [
            ':state'       => 'retryable_failed',
            ':processing'  => 'processing',
            ':next_attempt'=> $next,
            ':http_code'   => $http_code,
            ':error_class' => self::redact($error_class),
            ':error'       => self::redact($error),
            ':now'         => gmdate('c'),
            ':id'          => $delivery_id,
        ];
        if ($token_sql !== '') {
            $params[':token'] = $claim_token;
        }
        $stmt->execute($params);
        $changed = $stmt->rowCount() === 1;
        if ($changed) {
            $this->sync_source_email_status($delivery_id, 'retryable_failed');
        }
        return $changed;
    }

    public function find_for_reconciliation(int $limit = 50): array {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->query(
            "SELECT * FROM inquiry_deliveries
             WHERE state = 'acceptance_unknown'
             ORDER BY \"updatedAt\" ASC
             LIMIT " . $limit
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Elimina toda referencia reversible de entregas terminales vencidas.
     */
    public function anonymize_due_deliveries(int $days = 90): int {
        $days = max(1, $days);
        $cutoff = gmdate('c', time() - ($days * 86400));
        $now = gmdate('c');
        $owns_transaction = !$this->pdo->inTransaction();

        if ($owns_transaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $select = $this->pdo->query(
                "SELECT id, \"snapshotId\", \"terminalAt\"
                 FROM inquiry_deliveries
                 WHERE \"anonymizedAt\" IS NULL
                   AND \"terminalAt\" IS NOT NULL
                   AND state IN ('accepted','acceptance_unknown','failed','blocked')"
            );
            $candidates = $select ? $select->fetchAll(PDO::FETCH_ASSOC) : [];
            $cutoff_ts = strtotime($cutoff);
            $due = array_values(array_filter($candidates, static function(array $row) use ($cutoff_ts): bool {
                $terminal_ts = strtotime((string) ($row['terminalAt'] ?? ''));
                return $terminal_ts !== false && $cutoff_ts !== false && $terminal_ts < $cutoff_ts;
            }));

            if (empty($due)) {
                if ($owns_transaction) {
                    $this->pdo->commit();
                }
                return 0;
            }

            $update = $this->pdo->prepare(
                'UPDATE inquiry_deliveries
                 SET "snapshotId" = NULL, "inquiryId" = NULL, "consultaId" = NULL, email = NULL,
                     "contactId" = NULL, "payloadJson" = NULL, "claimToken" = NULL,
                     "lastError" = NULL, "anonymizedAt" = :now, "updatedAt" = :now
                 WHERE id = :id AND "anonymizedAt" IS NULL'
            );
            $delete_snapshot = $this->pdo->prepare(
                'DELETE FROM inquiry_snapshots WHERE id = :snapshot_id'
            );

            $count = 0;
            foreach ($due as $row) {
                $update->execute([':now' => $now, ':id' => (string) $row['id']]);
                if ($update->rowCount() !== 1) {
                    continue;
                }
                $count++;
                $snapshot_id = trim((string) ($row['snapshotId'] ?? ''));
                if ($snapshot_id !== '') {
                    $delete_snapshot->execute([':snapshot_id' => $snapshot_id]);
                }
            }

            if ($owns_transaction) {
                $this->pdo->commit();
            }
            return $count;
        } catch (Throwable $e) {
            if ($owns_transaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function transition_terminal(
        string $delivery_id,
        string $state,
        ?int $http_code = null,
        ?string $error_class = null,
        ?string $error = null,
        bool $accepted = false,
        ?string $claim_token = null
    ): bool {
        $now = gmdate('c');
        $token_sql = ($claim_token !== null && $claim_token !== '') ? ' AND "claimToken" = :token' : '';
        $stmt = $this->pdo->prepare(
            'UPDATE inquiry_deliveries
             SET state = :state, "acceptedAt" = :accepted_at, "terminalAt" = :terminal_at,
                 "claimedAt" = NULL, "claimedUntil" = NULL, "claimToken" = NULL,
                 "lastHttpCode" = :http_code, "lastErrorClass" = :error_class,
                 "lastError" = :error, "updatedAt" = :now
             WHERE id = :id AND state = :processing' . $token_sql
        );
        $params = [
            ':state'        => $state,
            ':processing'   => 'processing',
            ':accepted_at'  => $accepted ? $now : null,
            ':terminal_at'  => $now,
            ':http_code'    => $http_code,
            ':error_class'  => self::redact($error_class),
            ':error'        => self::redact($error),
            ':now'          => $now,
            ':id'           => $delivery_id,
        ];
        if ($token_sql !== '') {
            $params[':token'] = $claim_token;
        }
        $stmt->execute($params);
        $changed = $stmt->rowCount() === 1;
        if ($changed) {
            $this->sync_source_email_status($delivery_id, $state);
        }
        return $changed;
    }

    private function sync_source_email_status(string $delivery_id, string $state): void {
        $stmt = $this->pdo->prepare(
            'SELECT "inquiryType", "inquiryId" FROM inquiry_deliveries WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $delivery_id]);
        $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$delivery || empty($delivery['inquiryId'])) {
            return;
        }

        $table = ($delivery['inquiryType'] ?? '') === 'seminar'
            ? 'seminar_inquiries'
            : (($delivery['inquiryType'] ?? '') === 'offer' ? 'offer_inquiries' : '');
        if ($table === '') {
            return;
        }

        $update = $this->pdo->prepare(
            'UPDATE ' . $table . '
             SET "emailStatus" = :status, "emailSender" = :sender, "updatedAt" = :updated_at
             WHERE id = :id'
        );
        $update->execute([
            ':status' => $state,
            ':sender' => 'mautic_transactional_queue',
            ':updated_at' => gmdate('c'),
            ':id' => (string) $delivery['inquiryId'],
        ]);
    }

    private function template_identity(array $snapshot): array {
        if (!class_exists('FLACSO_Mautic_Contract_Manifest')) {
            $manifest_file = dirname(__DIR__, 3) . '/modules/consultas/services/class-flacso-mautic-contract-manifest.php';
            if (is_file($manifest_file)) {
                require_once $manifest_file;
            }
        }

        if (class_exists('FLACSO_Mautic_Contract_Manifest')) {
            $template = method_exists('FLACSO_Mautic_Contract_Manifest', 'initial_template_for_snapshot')
                ? FLACSO_Mautic_Contract_Manifest::initial_template_for_snapshot($snapshot)
                : (FLACSO_Mautic_Contract_Manifest::definition()['template'] ?? []);

            return [
                'template_id'        => isset($template['id']) ? (int) $template['id'] : null,
                'functional_version' => (string) ($template['functional_version'] ?? ''),
                'content_sha256'     => (string) ($template['content_sha256'] ?? ''),
            ];
        }

        return [
            'template_id'        => null,
            'functional_version' => '',
            'content_sha256'     => '',
        ];
    }

    private static function json(array $value): string {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('No fue posible serializar el snapshot de consulta.');
        }
        return $json;
    }

    private static function redact(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $value);
        return function_exists('mb_substr') ? mb_substr((string) $value, 0, 500) : substr((string) $value, 0, 500);
    }
}
