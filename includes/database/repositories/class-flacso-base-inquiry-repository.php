<?php
/**
 * Repositorio base abstracto para consultas de FLACSO Uruguay.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

abstract class FLACSO_Base_Inquiry_Repository {
    protected ?string $table_name = null;
    private static array $columns_cache = [];

    public function __construct(?string $table_name = null) {
        $this->table_name = $table_name;
    }

    /**
     * Limpia la caché estática de columnas (útil en pruebas automatizadas).
     */
    public static function clear_cache(): void {
        self::$columns_cache = [];
    }

    /**
     * Genera un identificador con formato CUID (c + 24 caracteres alfanuméricos).
     */
    public static function generate_cuid(): string {
        return 'c' . substr(bin2hex(random_bytes(12)), 0, 24);
    }

    public function get_raw_table_name(): string {
        return $this->table_name ?? '';
    }

    public function get_table_name(): string {
        return '"' . trim($this->get_raw_table_name(), '"') . '"';
    }

    /**
     * Obtiene la lista de columnas existentes en la tabla para evitar errores de columnas faltantes.
     */
    public function get_table_columns(): array {
        $table = trim($this->get_raw_table_name(), '"');
        $pdo = FLACSO_DB::connection();
        $pdo_id = spl_object_id($pdo);
        $cache_key = "{$pdo_id}:{$table}";

        if (isset(self::$columns_cache[$cache_key])) {
            return self::$columns_cache[$cache_key];
        }

        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $columns = [];

            if ($driver === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info(\"{$table}\")");
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        if (isset($row['name'])) {
                            $columns[] = $row['name'];
                        }
                    }
                }
            } else {
                $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_name = :tbl');
                $stmt->execute([':tbl' => $table]);
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            if (!empty($columns)) {
                return self::$columns_cache[$cache_key] = $columns;
            }
        } catch (\Throwable $e) {
            // Continuar sin cache de columnas
        }

        return [];
    }

    /**
     * Busca un registro por consultaId.
     */
    public function find_by_consulta_id(string $consulta_id): ?array {
        if ($consulta_id === '') {
            return null;
        }

        $pdo = FLACSO_DB::connection();
        $table = $this->get_table_name();
        $sql = "SELECT * FROM {$table} WHERE \"consultaId\" = :consulta_id LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':consulta_id' => $consulta_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Actualiza el estado de envío de correo Mailjet para una consulta.
     */
    public function update_email_status(
        string $consulta_id,
        string $status,
        ?string $sender = null,
        ?string $message_id = null,
        ?string $message_uuid = null
    ): bool {
        if ($consulta_id === '') {
            return false;
        }

        $pdo = FLACSO_DB::connection();
        $table = $this->get_table_name();
        $available_cols = $this->get_table_columns();

        $fields = ['"emailStatus" = :status', '"updatedAt" = :updated_at'];
        $params = [
            ':status'      => $status,
            ':updated_at'  => gmdate('c'),
            ':consulta_id' => $consulta_id,
        ];

        if ($sender !== null && (empty($available_cols) || in_array('emailSender', $available_cols, true))) {
            $fields[] = '"emailSender" = :sender';
            $params[':sender'] = $sender;
        }
        if ($message_id !== null && (empty($available_cols) || in_array('mailjetMessageId', $available_cols, true))) {
            $fields[] = '"mailjetMessageId" = :message_id';
            $params[':message_id'] = $message_id;
        }
        if ($message_uuid !== null && (empty($available_cols) || in_array('mailjetMessageUuid', $available_cols, true))) {
            $fields[] = '"mailjetMessageUuid" = :message_uuid';
            $params[':message_uuid'] = $message_uuid;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE \"consultaId\" = :consulta_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Verifica si una excepción PDO se debe a una violación de restricción de unicidad.
     */
    protected function is_unique_violation(PDOException $e): bool {
        $code = (string)$e->getCode();
        if ($code === '23505' || $code === '23000' || $e->getCode() === 19) {
            return true;
        }
        $message = strtolower($e->getMessage());
        return str_contains($message, 'unique constraint') ||
               str_contains($message, 'duplicate key') ||
               str_contains($message, '23505');
    }
}
