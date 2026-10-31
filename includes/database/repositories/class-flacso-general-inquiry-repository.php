<?php
/**
 * Repositorio para la tabla general_inquiries (formulario de contacto).
 *
 * La tabla heredada no tiene un esquema versionado en este repositorio, así
 * que sólo se escriben las columnas que existen realmente.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

require_once __DIR__ . '/class-flacso-base-inquiry-repository.php';

class FLACSO_General_Inquiry_Repository extends FLACSO_Base_Inquiry_Repository {
    public function __construct(string $table_name = 'general_inquiries') {
        parent::__construct($table_name);
    }

    /**
     * Inserta una consulta general. Es idempotente por consultaId.
     *
     * @return array{id:string,consultaId:string,duplicate:bool}
     */
    public function insert(array $data): array {
        $consulta_id = trim((string) ($data['consultaId'] ?? ''));
        if ($consulta_id !== '') {
            $existing = $this->find_by_consulta_id($consulta_id);
            if ($existing !== null) {
                return ['id' => (string) $existing['id'], 'consultaId' => $consulta_id, 'duplicate' => true];
            }
        }

        $first = trim((string) ($data['firstName'] ?? ''));
        $last = trim((string) ($data['lastName'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $now = gmdate('c');
        $id = !empty($data['id']) ? (string) $data['id'] : self::generate_cuid();

        $record = [
            'id'                 => $id,
            'consultaId'         => $consulta_id,
            'firstName'          => $first,
            'lastName'           => $last,
            'fullName'           => trim($first . ' ' . $last),
            'email'              => $email,
            'emailNormalized'    => strtolower($email),
            'phone'              => (string) ($data['phone'] ?? ''),
            'subject'            => (string) ($data['subject'] ?? ''),
            'message'            => (string) ($data['message'] ?? ''),
            'unit'               => (string) ($data['unit'] ?? 'Secretaría'),
            'source'             => (string) ($data['source'] ?? 'Contacto'),
            'campaignProvider'   => $data['campaignProvider'] ?? null,
            'campaignSource'     => $data['campaignSource'] ?? null,
            'campaignMedium'     => $data['campaignMedium'] ?? null,
            'campaignName'       => $data['campaignName'] ?? null,
            'campaignExternalId' => $data['campaignExternalId'] ?? null,
            'campaignContent'    => $data['campaignContent'] ?? null,
            'campaignTerm'       => $data['campaignTerm'] ?? null,
            'urlReferer'         => $data['urlReferer'] ?? null,
            'inquiryAt'          => (string) ($data['inquiryAt'] ?? $now),
            'ipAddress'          => $data['ipAddress'] ?? null,
            'userAgent'          => $data['userAgent'] ?? null,
            'emailStatus'        => (string) ($data['emailStatus'] ?? 'pending'),
            'payload'            => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'createdAt'          => $now,
            'updatedAt'          => $now,
        ];

        $available = $this->get_table_columns();
        if ($available === []) {
            throw new RuntimeException('La tabla general_inquiries no existe o no tiene columnas visibles.');
        }
        $record = array_filter($record, static fn($key) => in_array($key, $available, true), ARRAY_FILTER_USE_KEY);

        $columns_sql = implode(', ', array_map(static fn($col) => "\"{$col}\"", array_keys($record)));
        $placeholders_sql = implode(', ', array_map(static fn($col) => ":{$col}", array_keys($record)));
        $sql = 'INSERT INTO ' . $this->get_table_name() . " ({$columns_sql}) VALUES ({$placeholders_sql})";
        $params = [];
        foreach ($record as $col => $value) {
            $params[":{$col}"] = $value;
        }

        try {
            FLACSO_DB::connection()->prepare($sql)->execute($params);
        } catch (PDOException $error) {
            if ($consulta_id !== '' && $this->is_unique_violation($error)) {
                $existing = $this->find_by_consulta_id($consulta_id);
                if ($existing !== null) {
                    return ['id' => (string) $existing['id'], 'consultaId' => $consulta_id, 'duplicate' => true];
                }
            }
            throw $error;
        }

        return ['id' => $id, 'consultaId' => $consulta_id, 'duplicate' => false];
    }
}
