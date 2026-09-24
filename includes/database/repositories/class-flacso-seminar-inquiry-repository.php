<?php
/**
 * Repositorio para la tabla seminar_inquiries.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

require_once __DIR__ . '/class-flacso-base-inquiry-repository.php';

class FLACSO_Seminar_Inquiry_Repository extends FLACSO_Base_Inquiry_Repository {
    public function __construct(string $table_name = 'seminar_inquiries') {
        parent::__construct($table_name);
    }

    /**
     * Inserta una consulta de seminario con verificación temprana de idempotencia
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
            ? (is_string($data['payload']) ? $data['payload'] : json_encode($data['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $now = gmdate('c');

        $record = [
            'id'                  => $id,
            'consultaId'          => $consulta_id,
            'seminarWpId'         => isset($data['seminarWpId']) && $data['seminarWpId'] !== '' ? (int)$data['seminarWpId'] : null,
            'seminarName'         => isset($data['seminarName']) ? (string)$data['seminarName'] : '',
            'seminarType'         => isset($data['seminarType']) ? (string)$data['seminarType'] : null,
            'firstName'           => $first_name,
            'lastName'            => $last_name,
            'fullName'            => $full_name,
            'email'               => $email,
            'emailNormalized'     => $email_norm,
            'country'             => isset($data['country']) ? (string)$data['country'] : null,
            'profession'          => isset($data['profession']) ? (string)$data['profession'] : null,
            'educationLevel'      => isset($data['educationLevel']) ? (string)$data['educationLevel'] : null,
            'source'              => isset($data['source']) ? (string)$data['source'] : 'Seminario',
            'campaignProvider'    => isset($data['campaignProvider']) ? (string)$data['campaignProvider'] : null,
            'campaignSource'      => isset($data['campaignSource']) ? (string)$data['campaignSource'] : null,
            'campaignMedium'      => isset($data['campaignMedium']) ? (string)$data['campaignMedium'] : null,
            'campaignName'        => isset($data['campaignName']) ? (string)$data['campaignName'] : null,
            'campaignExternalId'  => isset($data['campaignExternalId']) ? (string)$data['campaignExternalId'] : null,
            'campaignContent'     => isset($data['campaignContent']) ? (string)$data['campaignContent'] : null,
            'campaignTerm'        => isset($data['campaignTerm']) ? (string)$data['campaignTerm'] : null,
            'urlBase'             => isset($data['urlBase']) ? (string)$data['urlBase'] : null,
            'urlReferer'          => isset($data['urlReferer']) ? (string)$data['urlReferer'] : null,
            'inquiryAt'           => isset($data['inquiryAt']) ? (string)$data['inquiryAt'] : $now,
            'ipAddress'           => isset($data['ipAddress']) ? (string)$data['ipAddress'] : null,
            'userAgent'           => isset($data['userAgent']) ? (string)$data['userAgent'] : null,
            'replyToEmail'        => isset($data['replyToEmail']) ? (string)$data['replyToEmail'] : null,
            'programUrl'          => isset($data['programUrl']) ? (string)$data['programUrl'] : null,
            'cartaUrl'            => isset($data['cartaUrl']) ? (string)$data['cartaUrl'] : null,
            'preinscripcionUrl'   => isset($data['preinscripcionUrl']) ? (string)$data['preinscripcionUrl'] : null,
            'offerStatus'         => isset($data['offerStatus']) ? (string)$data['offerStatus'] : null,
            'emailStatus'         => isset($data['emailStatus']) ? (string)$data['emailStatus'] : 'skipped',
            'emailSender'         => isset($data['emailSender']) ? (string)$data['emailSender'] : null,
            'gmailMessageUrl'     => isset($data['gmailMessageUrl']) ? (string)$data['gmailMessageUrl'] : null,
            'mailjetMessageId'    => isset($data['mailjetMessageId']) ? (string)$data['mailjetMessageId'] : null,
            'mailjetMessageUuid'  => isset($data['mailjetMessageUuid']) ? (string)$data['mailjetMessageUuid'] : null,
            'payload'             => $payload,
            'createdAt'           => isset($data['createdAt']) ? (string)$data['createdAt'] : $now,
            'updatedAt'           => isset($data['updatedAt']) ? (string)$data['updatedAt'] : $now,
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
}
