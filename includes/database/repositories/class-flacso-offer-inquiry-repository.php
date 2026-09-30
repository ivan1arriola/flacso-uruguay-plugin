<?php
/**
 * Repositorio para la tabla offer_inquiries.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

require_once __DIR__ . '/class-flacso-base-inquiry-repository.php';

class FLACSO_Offer_Inquiry_Repository extends FLACSO_Base_Inquiry_Repository {
    public function __construct(string $table_name = 'offer_inquiries') {
        parent::__construct($table_name);
    }

    /**
     * Inserta una consulta de oferta académica con verificación temprana de idempotencia
     * y recuperación ante condiciones de carrera en unicidad.
     */
    public function insert(array $data): array {
        $consulta_id = isset($data['consultaId']) ? (string)$data['consultaId'] : '';

        // 1. Verificación temprana de idempotencia
        if ($consulta_id !== '') {
            $existing = $this->find_by_consulta_id($consulta_id);
            if ($existing !== null) {
                return [
                    'id'         => (string)$existing['id'],
                    'consultaId' => $consulta_id,
                    'duplicate'  => true,
                ];
            }
        }

        // 2. Generar CUID
        $id = !empty($data['id']) ? (string)$data['id'] : self::generate_cuid();

        // 3. Normalizar campos
        $first_name = isset($data['firstName']) ? (string)$data['firstName'] : '';
        $last_name  = isset($data['lastName']) ? (string)$data['lastName'] : '';
        $full_name  = !empty($data['fullName']) ? (string)$data['fullName'] : trim($first_name . ' ' . $last_name);
        $email      = isset($data['email']) ? (string)$data['email'] : '';
        $email_norm = !empty($data['emailNormalized']) ? strtolower(trim((string)$data['emailNormalized'])) : strtolower(trim($email));

        $payload = isset($data['payload'])
            ? (is_string($data['payload']) ? (trim($data['payload']) !== '' ? $data['payload'] : '{}') : (json_encode($data['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'))
            : (json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        $now = gmdate('c');
        $inquiry_at = (!empty($data['inquiryAt']) && trim((string)$data['inquiryAt']) !== '') ? trim((string)$data['inquiryAt']) : $now;
        $created_at = (!empty($data['createdAt']) && trim((string)$data['createdAt']) !== '') ? trim((string)$data['createdAt']) : $now;
        $updated_at = (!empty($data['updatedAt']) && trim((string)$data['updatedAt']) !== '') ? trim((string)$data['updatedAt']) : $now;

        $record = [
            'id'                  => $id,
            'consultaId'          => $consulta_id,
            'offerWpId'           => isset($data['offerWpId']) && $data['offerWpId'] !== '' ? (int)$data['offerWpId'] : null,
            'offerName'           => isset($data['offerName']) ? (string)$data['offerName'] : '',
            'offerAbbreviation'   => isset($data['offerAbbreviation']) && trim((string)$data['offerAbbreviation']) !== '' ? (string)$data['offerAbbreviation'] : null,
            'offerType'           => isset($data['offerType']) ? (string)$data['offerType'] : null,
            'cohortWpId'          => isset($data['cohortWpId']) && $data['cohortWpId'] !== '' ? (int)$data['cohortWpId'] : null,
            'cohortNumber'        => isset($data['cohortNumber']) && $data['cohortNumber'] !== '' ? (int)$data['cohortNumber'] : null,
            'cohortName'          => isset($data['cohortName']) ? (string)$data['cohortName'] : null,
            'registrationOpenAt'  => isset($data['registrationOpenAt']) && trim((string)$data['registrationOpenAt']) !== '' ? (string)$data['registrationOpenAt'] : null,
            'registrationCloseAt' => isset($data['registrationCloseAt']) && trim((string)$data['registrationCloseAt']) !== '' ? (string)$data['registrationCloseAt'] : null,
            'firstName'           => $first_name,
            'lastName'            => $last_name,
            'fullName'            => $full_name,
            'email'               => $email,
            'emailNormalized'     => $email_norm,
            'country'             => isset($data['country']) ? (string)$data['country'] : null,
            'profession'          => isset($data['profession']) ? (string)$data['profession'] : null,
            'educationLevel'      => isset($data['educationLevel']) ? (string)$data['educationLevel'] : null,
            'source'              => isset($data['source']) ? (string)$data['source'] : 'Web',
            'campaignProvider'    => isset($data['campaignProvider']) ? (string)$data['campaignProvider'] : null,
            'campaignSource'      => isset($data['campaignSource']) ? (string)$data['campaignSource'] : null,
            'campaignMedium'      => isset($data['campaignMedium']) ? (string)$data['campaignMedium'] : null,
            'campaignName'        => isset($data['campaignName']) ? (string)$data['campaignName'] : null,
            'campaignExternalId'  => isset($data['campaignExternalId']) ? (string)$data['campaignExternalId'] : null,
            'campaignContent'     => isset($data['campaignContent']) ? (string)$data['campaignContent'] : null,
            'campaignTerm'        => isset($data['campaignTerm']) ? (string)$data['campaignTerm'] : null,
            'urlBase'             => isset($data['urlBase']) ? (string)$data['urlBase'] : null,
            'urlReferer'          => isset($data['urlReferer']) ? (string)$data['urlReferer'] : null,
            'inquiryAt'           => $inquiry_at,
            'ipAddress'           => isset($data['ipAddress']) ? (string)$data['ipAddress'] : null,
            'userAgent'           => isset($data['userAgent']) ? (string)$data['userAgent'] : null,
            'replyToEmail'        => isset($data['replyToEmail']) ? (string)$data['replyToEmail'] : null,
            'programUrl'          => isset($data['programUrl']) ? (string)$data['programUrl'] : null,
            'cartaUrl'            => isset($data['cartaUrl']) ? (string)$data['cartaUrl'] : null,
            'preinscripcionUrl'   => isset($data['preinscripcionUrl']) ? (string)$data['preinscripcionUrl'] : null,
            'offerStatus'         => isset($data['offerStatus']) ? (string)$data['offerStatus'] : 'sin_cohorte',
            'mauticContactId'     => isset($data['mauticContactId']) ? (string)$data['mauticContactId'] : null,
            'mauticSyncStatus'    => isset($data['mauticSyncStatus']) ? (string)$data['mauticSyncStatus'] : 'skipped',
            'mauticSyncedAt'      => isset($data['mauticSyncedAt']) ? (string)$data['mauticSyncedAt'] : null,
            'mauticLastError'     => isset($data['mauticLastError']) ? (string)$data['mauticLastError'] : null,
            'followupDueAt'       => isset($data['followupDueAt']) ? (string)$data['followupDueAt'] : null,
            'followupStatus'      => isset($data['followupStatus']) ? (string)$data['followupStatus'] : 'none',
            'followupSentAt'      => isset($data['followupSentAt']) ? (string)$data['followupSentAt'] : null,
            'followupAttempts'    => isset($data['followupAttempts']) ? (int)$data['followupAttempts'] : 0,
            'followupLastError'   => isset($data['followupLastError']) ? (string)$data['followupLastError'] : null,
            'emailStatus'         => isset($data['emailStatus']) ? (string)$data['emailStatus'] : 'skipped',
            'emailSender'         => isset($data['emailSender']) ? (string)$data['emailSender'] : null,
            'gmailMessageUrl'     => isset($data['gmailMessageUrl']) ? (string)$data['gmailMessageUrl'] : null,
            'mailjetMessageId'    => isset($data['mailjetMessageId']) ? (string)$data['mailjetMessageId'] : null,
            'mailjetMessageUuid'  => isset($data['mailjetMessageUuid']) ? (string)$data['mailjetMessageUuid'] : null,
            'payload'             => $payload,
            'createdAt'           => $created_at,
            'updatedAt'           => $updated_at,
        ];

        // 4. Filtrar por columnas existentes en la tabla
        $available_cols = $this->get_table_columns();
        if (!empty($available_cols)) {
            $record = array_filter(
                $record,
                static fn($key) => in_array($key, $available_cols, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        // 5. Preparar SQL e insertar
        $columns_sql = implode(', ', array_map(static fn($col) => "\"{$col}\"", array_keys($record)));
        $placeholders_sql = implode(', ', array_map(static fn($col) => ":{$col}", array_keys($record)));
        $table = $this->get_table_name();
        $sql = "INSERT INTO {$table} ({$columns_sql}) VALUES ({$placeholders_sql})";

        $params = [];
        foreach ($record as $col => $val) {
            $params[":{$col}"] = $val;
        }

        $pdo = FLACSO_DB::connection();

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            // Recuperar si ocurrió colisión por condición de carrera
            if ($this->is_unique_violation($e)) {
                $existing = $this->find_by_consulta_id($consulta_id);
                if ($existing !== null) {
                    return [
                        'id'         => (string)$existing['id'],
                        'consultaId' => $consulta_id,
                        'duplicate'  => true,
                    ];
                }
            }
            throw $e;
        }

        return [
            'id'         => $id,
            'consultaId' => $consulta_id,
            'duplicate'  => false,
        ];
    }

    /**
     * Busca la consulta más reciente de una cohorte y correo con seguimiento pendiente.
     */
    public function find_pending_by_email_and_cohort(string $email_normalized, int $cohort_id): ?array {
        $email_norm = strtolower(trim($email_normalized));
        if ($email_norm === '' || $cohort_id <= 0) {
            return null;
        }
        $pdo = FLACSO_DB::connection();
        $table = $this->get_table_name();
        $sql = "SELECT * FROM {$table} WHERE \"emailNormalized\" = :email AND \"cohortWpId\" = :cohort_id AND \"followupStatus\" = 'pending' ORDER BY \"createdAt\" DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email_norm, ':cohort_id' => $cohort_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Busca un registro por su ID primario.
     */
    public function find_by_id(string $id): ?array {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $pdo = FLACSO_DB::connection();
        $table = $this->get_table_name();
        $sql = "SELECT * FROM {$table} WHERE \"id\" = :id LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Actualiza atómicamente el estado de sincronización y el ID de contacto de Mautic.
     *
     * @param string $id UUID / CUID de la consulta en offer_inquiries.
     * @param array  $mautic_data Datos a actualizar (mauticContactId, mauticSyncStatus, mauticSyncedAt, mauticLastError).
     * @return bool True si se actualizó con éxito al menos un registro, false en caso contrario.
     */
    public function update_mautic_status(string $id, array $mautic_data): bool {
        $id = trim($id);
        if ($id === '' || empty($mautic_data)) {
            return false;
        }

        $allowed_fields = [
            'mauticContactId',
            'mauticSyncStatus',
            'mauticSyncedAt',
            'mauticLastError',
        ];

        $fields = [];
        $params = [
            ':id'         => $id,
            ':updated_at' => gmdate('c'),
        ];

        $available_cols = $this->get_table_columns();
        $valid_field_count = 0;

        foreach ($allowed_fields as $col) {
            if (!array_key_exists($col, $mautic_data)) {
                continue;
            }

            if (!empty($available_cols) && !in_array($col, $available_cols, true)) {
                continue;
            }

            $val = $mautic_data[$col];

            if ($col === 'mauticSyncStatus') {
                $allowed_statuses = ['synced', 'failed', 'skipped', 'pending'];
                if (!is_string($val) || !in_array($val, $allowed_statuses, true)) {
                    continue;
                }
            } elseif ($col === 'mauticContactId') {
                if ($val !== null) {
                    $val = (string)$val;
                }
            } elseif ($col === 'mauticSyncedAt' || $col === 'mauticLastError') {
                if ($val !== null) {
                    $val = (string)$val;
                }
            }

            $fields[] = "\"{$col}\" = :{$col}";
            $params[":{$col}"] = $val;
            $valid_field_count++;
        }

        if ($valid_field_count === 0) {
            return false;
        }

        $fields[] = '"updatedAt" = :updated_at';

        $table = $this->get_table_name();
        $sql = "UPDATE {$table} SET " . implode(', ', $fields) . ' WHERE "id" = :id';

        try {
            $pdo = FLACSO_DB::connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Registra el resultado de la incorporación de una consulta a una campaña de Mautic.
     * Los datos personales y el snapshot de la consulta nunca se modifican aquí.
     *
     * @param string $id ID CUID del registro en offer_inquiries.
     * @param array $state mauticCampaignId, mauticCampaignStatus, mauticCampaignAttemptedAt,
     *                     mauticCampaignAttempts (ignorado, se controla internamente) y mauticCampaignLastError.
     */
    public function update_mautic_campaign_status(string $id, array $state): bool {
        $id = trim($id);
        if ($id === '' || empty($state)) {
            return false;
        }

        $available_cols = $this->get_table_columns();
        $required_columns = [
            'mauticCampaignId',
            'mauticCampaignStatus',
            'mauticCampaignAttemptedAt',
            'mauticCampaignAttempts',
            'mauticCampaignLastError',
        ];
        foreach ($required_columns as $column) {
            if (!in_array($column, $available_cols, true)) {
                return false;
            }
        }

        $status = $state['mauticCampaignStatus'] ?? null;
        $allowed_statuses = ['pending', 'joined', 'failed', 'skipped'];
        if (!is_string($status) || !in_array($status, $allowed_statuses, true)) {
            return false;
        }

        $fields = ['"mauticCampaignStatus" = :status'];
        $params = [
            ':id' => $id,
            ':status' => $status,
            ':updated_at' => gmdate('c'),
            ':increment_attempt' => $status === 'pending' ? 0 : 1,
        ];

        if (array_key_exists('mauticCampaignId', $state)) {
            $campaign_id = $state['mauticCampaignId'];
            if ($campaign_id !== null && (!is_numeric($campaign_id) || (int) $campaign_id <= 0)) {
                return false;
            }
            $fields[] = '"mauticCampaignId" = :campaign_id';
            $params[':campaign_id'] = $campaign_id === null ? null : (int) $campaign_id;
        }

        if (array_key_exists('mauticCampaignLastError', $state)) {
            $fields[] = '"mauticCampaignLastError" = :last_error';
            $params[':last_error'] = $state['mauticCampaignLastError'] === null
                ? null
                : (string) $state['mauticCampaignLastError'];
        }

        if (array_key_exists('mauticCampaignAttemptedAt', $state)) {
            $attempted_at = $state['mauticCampaignAttemptedAt'];
            $fields[] = '"mauticCampaignAttemptedAt" = :attempted_at';
            $params[':attempted_at'] = $attempted_at === null ? null : (string) $attempted_at;
        } elseif ($status !== 'pending') {
            $fields[] = '"mauticCampaignAttemptedAt" = :attempted_at';
            $params[':attempted_at'] = gmdate('c');
        }

        $fields[] = '"mauticCampaignAttempts" = CASE WHEN CAST(:increment_attempt AS INTEGER) = 1 THEN CASE WHEN COALESCE("mauticCampaignAttempts", 0) < 3 THEN COALESCE("mauticCampaignAttempts", 0) + 1 ELSE 3 END ELSE COALESCE("mauticCampaignAttempts", 0) END';
        $fields[] = '"updatedAt" = :updated_at';

        $table = $this->get_table_name();
        $sql = "UPDATE {$table} SET " . implode(', ', $fields) . ' WHERE "id" = :id';

        try {
            $pdo = FLACSO_DB::connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('[FLACSO] Error en update_mautic_campaign_status: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reclama atómicamente hasta $limit consultas vencidas transicionándolas de 'pending' a 'processing'.
     *
     * @param int $limit Máximo de registros a reclamar por ciclo (defecto 25, máx 100).
     * @return array Lista de registros asociativos completos reclamados.
     */
    public function claim_due_followups(int $limit = 25): array {
        $limit = max(1, min(100, (int)$limit));
        $now = gmdate('Y-m-d H:i:s');
        $table = $this->get_table_name();

        try {
            $pdo = FLACSO_DB::connection();

            $select_sql = "SELECT * FROM {$table}
                           WHERE \"followupStatus\" = 'pending'
                             AND \"followupDueAt\" IS NOT NULL
                             AND \"followupDueAt\" <= :now
                             AND COALESCE(\"followupAttempts\", 0) < 3
                           ORDER BY \"followupDueAt\" ASC
                           LIMIT " . $limit;

            $stmt = $pdo->prepare($select_sql);
            $stmt->execute([':now' => $now]);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($candidates)) {
                return [];
            }

            $update_sql = "UPDATE {$table}
                           SET \"followupStatus\" = 'processing',
                               \"updatedAt\" = :updated_at
                           WHERE \"id\" = :id
                             AND \"followupStatus\" = 'pending'";
            $update_stmt = $pdo->prepare($update_sql);

            $claimed = [];
            $updated_at = gmdate('c');

            foreach ($candidates as $candidate) {
                $candidate_id = (string)$candidate['id'];
                $update_stmt->execute([
                    ':id'         => $candidate_id,
                    ':updated_at' => $updated_at,
                ]);

                if ($update_stmt->rowCount() > 0) {
                    $candidate['followupStatus'] = 'processing';
                    $candidate['updatedAt'] = $updated_at;
                    $claimed[] = $candidate;
                }
            }

            return $claimed;
        } catch (\Throwable $e) {
            error_log('[FLACSO] Error en claim_due_followups: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Actualiza el resultado del seguimiento de una consulta.
     *
     * @param string      $id       ID CUID del registro.
     * @param string      $status   'pending' | 'processing' | 'sent' | 'skipped' | 'failed' | 'none'
     * @param string|null $error    Mensaje de error o motivo de descarte.
     * @param string|null $sent_at  Timestamp ISO-8601 del envío.
     * @return bool True si se actualizó el registro, false en caso contrario.
     */
    public function update_followup_status(string $id, string $status, ?string $error = null, ?string $sent_at = null): bool {
        $id = trim($id);
        if ($id === '') {
            return false;
        }

        $allowed_statuses = ['pending', 'processing', 'sent', 'skipped', 'failed', 'none'];
        if (!in_array($status, $allowed_statuses, true)) {
            return false;
        }

        if (($sent_at === null || trim($sent_at) === '') && $status === 'sent') {
            $sent_at = gmdate('c');
        } elseif ($sent_at !== null) {
            $sent_at = trim($sent_at) !== '' ? trim($sent_at) : null;
        }

        $error = ($error !== null && trim($error) !== '') ? trim($error) : null;
        $updated_at = gmdate('c');

        $table = $this->get_table_name();
        $sql = "UPDATE {$table}
                SET \"followupStatus\" = :status,
                    \"followupAttempts\" = COALESCE(\"followupAttempts\", 0) + 1,
                    \"followupLastError\" = :error,
                    \"followupSentAt\" = :sent_at,
                    \"updatedAt\" = :updated_at
                WHERE \"id\" = :id";

        try {
            $pdo = FLACSO_DB::connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id'         => $id,
                ':status'     => $status,
                ':error'      => $error,
                ':sent_at'    => $sent_at,
                ':updated_at' => $updated_at,
            ]);

            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('[FLACSO] Error en update_followup_status: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Determina si existe una consulta posterior del mismo correo para la misma oferta.
     *
     * @param string $email_normalized Correo electrónico normalizado.
     * @param int    $offer_wp_id      ID de WordPress de la oferta académica.
     * @param string $current_inquiry_at Timestamp de la consulta actual a comparar.
     * @return bool True si existe al menos una consulta posterior, false en caso contrario.
     */
    public function has_newer_inquiry_for_offer(string $email_normalized, int $offer_wp_id, string $current_inquiry_at): bool {
        $email_norm = strtolower(trim($email_normalized));
        $current_inquiry_at = trim($current_inquiry_at);

        if ($email_norm === '' || $offer_wp_id <= 0 || $current_inquiry_at === '') {
            return false;
        }

        $table = $this->get_table_name();
        $sql = "SELECT 1 FROM {$table}
                WHERE \"emailNormalized\" = :email
                  AND \"offerWpId\" = :offer_id
                  AND \"inquiryAt\" > :current_at
                LIMIT 1";

        try {
            $pdo = FLACSO_DB::connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':email'      => $email_norm,
                ':offer_id'   => $offer_wp_id,
                ':current_at' => $current_inquiry_at,
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[FLACSO] Error en has_newer_inquiry_for_offer: ' . $e->getMessage());
            return false;
        }
    }
}
