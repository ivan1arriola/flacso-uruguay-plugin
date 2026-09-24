<?php
/**
 * Repositorio para consultas generales (tabla general_inquiries o Consulta).
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

require_once __DIR__ . '/class-flacso-base-inquiry-repository.php';

class FLACSO_General_Inquiry_Repository extends FLACSO_Base_Inquiry_Repository {
    public function __construct(?string $table_name = null) {
        parent::__construct($table_name);
    }

    public function get_raw_table_name(): string {
        if ($this->table_name !== null && $this->table_name !== '') {
            return $this->table_name;
        }

        return $this->table_name = self::resolve_table_name();
    }

    /**
     * Resuelve dinámicamente si la tabla es general_inquiries (canónica en PostgreSQL Prisma)
     * o Consulta (compatibilidad histórica / SQLite tests).
     */
    public static function resolve_table_name(): string {
        try {
            $pdo = FLACSO_DB::connection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('general_inquiries', 'Consulta') ORDER BY CASE WHEN name = 'general_inquiries' THEN 1 ELSE 2 END LIMIT 1");
                $name = $stmt ? $stmt->fetchColumn() : false;
                if ($name) {
                    return (string)$name;
                }
            } else {
                $stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename IN ('general_inquiries', 'consulta', 'Consulta') ORDER BY CASE WHEN tablename = 'general_inquiries' THEN 1 ELSE 2 END LIMIT 1");
                $name = $stmt ? $stmt->fetchColumn() : false;
                if ($name) {
                    return (string)$name;
                }
            }
        } catch (\Throwable $e) {
            // Silencioso ante fallos de metadatos
        }

        return 'general_inquiries';
    }

    /**
     * Inserta una consulta general con verificación temprana de idempotencia,
     * generación de controlNumber y recuperación de concurrencia.
     */
    public function insert(array $data): array {
        $consulta_id = isset($data['consultaId']) ? (string)$data['consultaId'] : '';

        // 1. Verificación temprana de idempotencia
        if ($consulta_id !== '') {
            $existing = $this->find_by_consulta_id($consulta_id);
            if ($existing !== null) {
                return [
                    'id'            => (string)$existing['id'],
                    'consultaId'    => $consulta_id,
                    'controlNumber' => (string)($existing['controlNumber'] ?? ''),
                    'duplicate'     => true,
                ];
            }
        }

        $pdo = FLACSO_DB::connection();
        $table = $this->get_table_name();

        // 2. Generar o tomar controlNumber
        if (!empty($data['controlNumber'])) {
            $control_number = (string)$data['controlNumber'];
        } else {
            $stmt_count = $pdo->query("SELECT COUNT(*) FROM {$table}");
            $count = $stmt_count ? (int)$stmt_count->fetchColumn() : 0;
            $control_number = 'FC-' . str_pad((string)($count + 1), 6, '0', STR_PAD_LEFT);
        }

        // 3. Generar CUID
        $id = !empty($data['id']) ? (string)$data['id'] : self::generate_cuid();

        // 4. Normalizar campos
        $nombre     = isset($data['nombre']) ? (string)$data['nombre'] : '';
        $apellido   = isset($data['apellido']) ? (string)$data['apellido'] : '';
        $email      = isset($data['email']) ? (string)$data['email'] : '';
        $email_norm = !empty($data['emailNormalized']) ? strtolower(trim((string)$data['emailNormalized'])) : strtolower(trim($email));
        $asunto     = !empty($data['asunto']) ? (string)$data['asunto'] : "Consulta #{$control_number}";
        $mensaje    = isset($data['mensaje']) ? (string)$data['mensaje'] : '';
        $now        = gmdate('c');

        $record = [
            'id'                 => $id,
            'consultaId'         => $consulta_id,
            'controlNumber'      => $control_number,
            'nombre'             => $nombre,
            'apellido'           => $apellido,
            'email'              => $email,
            'emailNormalized'    => $email_norm,
            'telefono'           => isset($data['telefono']) ? (string)$data['telefono'] : null,
            'asunto'             => $asunto,
            'mensaje'            => $mensaje,
            'urlReferer'         => isset($data['urlReferer']) ? (string)$data['urlReferer'] : null,
            'ipAddress'          => isset($data['ipAddress']) ? (string)$data['ipAddress'] : null,
            'userAgent'          => isset($data['userAgent']) ? (string)$data['userAgent'] : null,
            'emailStatus'        => isset($data['emailStatus']) ? (string)$data['emailStatus'] : 'skipped',
            'emailSender'        => isset($data['emailSender']) ? (string)$data['emailSender'] : null,
            'mailjetMessageId'   => isset($data['mailjetMessageId']) ? (string)$data['mailjetMessageId'] : null,
            'mailjetMessageUuid' => isset($data['mailjetMessageUuid']) ? (string)$data['mailjetMessageUuid'] : null,
            'createdAt'          => isset($data['createdAt']) ? (string)$data['createdAt'] : $now,
            'updatedAt'          => isset($data['updatedAt']) ? (string)$data['updatedAt'] : $now,
        ];

        // 5. Filtrar por columnas existentes en la tabla
        $available_cols = $this->get_table_columns();
        if (!empty($available_cols)) {
            $record = array_filter(
                $record,
                static fn($key) => in_array($key, $available_cols, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        // 6. Preparar SQL e insertar
        $columns_sql = implode(', ', array_map(static fn($col) => "\"{$col}\"", array_keys($record)));
        $placeholders_sql = implode(', ', array_map(static fn($col) => ":{$col}", array_keys($record)));
        $sql = "INSERT INTO {$table} ({$columns_sql}) VALUES ({$placeholders_sql})";

        $params = [];
        foreach ($record as $col => $val) {
            $params[":{$col}"] = $val;
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            // Recuperar si ocurrió colisión por condición de carrera
            if ($this->is_unique_violation($e)) {
                $existing = $this->find_by_consulta_id($consulta_id);
                if ($existing !== null) {
                    return [
                        'id'            => (string)$existing['id'],
                        'consultaId'    => $consulta_id,
                        'controlNumber' => (string)($existing['controlNumber'] ?? ''),
                        'duplicate'     => true,
                    ];
                }
            }
            throw $e;
        }

        return [
            'id'            => $id,
            'consultaId'    => $consulta_id,
            'controlNumber' => $control_number,
            'duplicate'     => false,
        ];
    }
}
