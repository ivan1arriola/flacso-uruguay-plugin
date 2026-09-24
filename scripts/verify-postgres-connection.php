<?php
/**
 * Script de Diagnóstico No Destructivo de Conexión y Escritura a PostgreSQL.
 *
 * Valida conectividad, permisos de SELECT, INSERT dentro de transacción, UPDATE y ROLLBACK
 * contra las tablas `offer_inquiries` y `seminar_inquiries`.
 *
 * Invocación en producción:
 *   wp eval-file wp-content/plugins/flacso-uruguay-plugin/scripts/verify-postgres-connection.php
 *   o directamente:
 *   php scripts/verify-postgres-connection.php
 *
 * @package FLACSO_Uruguay
 */

// Nota: No incluir declare(strict_types=1) aquí porque WP-CLI (`wp eval-file`)
// evalúa el script mediante eval() y PHP lanza Fatal Error si aparece dentro de eval().

// Cargar configuración de WordPress si no están definidas las constantes de conexión
if (!defined('FLACSO_PG_HOST')) {
    $current_dir = __DIR__;
    for ($depth = 0; $depth < 6; $depth++) {
        if (file_exists($current_dir . '/wp-config.php')) {
            if (!defined('ABSPATH')) {
                define('ABSPATH', dirname($current_dir . '/wp-config.php') . '/');
            }
            require_once $current_dir . '/wp-config.php';
            break;
        }
        $parent = dirname($current_dir);
        if ($parent === $current_dir) {
            break;
        }
        $current_dir = $parent;
    }
}

// Cargar clases requeridas del plugin si no están cargadas
$plugin_root = dirname(__DIR__);
if (!class_exists('FLACSO_DB')) {
    require_once $plugin_root . '/includes/database/class-flacso-db.php';
}
if (!class_exists('FLACSO_Base_Inquiry_Repository')) {
    require_once $plugin_root . '/includes/database/repositories/class-flacso-base-inquiry-repository.php';
}
if (!class_exists('FLACSO_Offer_Inquiry_Repository')) {
    require_once $plugin_root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
}
if (!class_exists('FLACSO_Seminar_Inquiry_Repository')) {
    require_once $plugin_root . '/includes/database/repositories/class-flacso-seminar-inquiry-repository.php';
}

/**
 * Ejecuta el diagnóstico de PostgreSQL contra la base de datos configurada o inyectada.
 *
 * @param PDO|null $injected_pdo Conexión PDO opcional (p. ej. SQLite para tests).
 * @param bool     $verbose      Si es true, imprime las líneas de estado a la salida estándar.
 * @return array Array asociativo con resultados y estado de cada etapa.
 */
function flacso_run_postgres_verification(?PDO $injected_pdo = null, bool $verbose = false): array {
    $output_lines = [];
    $log = function(string $line) use (&$output_lines, $verbose): void {
        $output_lines[] = $line;
        if ($verbose) {
            echo $line . "\n";
        }
    };

    if ($injected_pdo !== null) {
        FLACSO_DB::set_connection($injected_pdo);
        $pdo = $injected_pdo;
    } else {
        if (!extension_loaded('pdo_pgsql')) {
            $msg = 'ERROR: La extensión PHP pdo_pgsql no está disponible en este servidor.';
            if ($verbose) {
                fwrite(STDERR, $msg . "\n");
            }
            return [
                'ok' => false,
                'error' => $msg,
                'summary' => $output_lines,
            ];
        }

        if (!FLACSO_DB::is_configured()) {
            $msg = 'ERROR: PostgreSQL no está configurado. Defina FLACSO_PG_HOST, FLACSO_PG_PORT, FLACSO_PG_DATABASE, FLACSO_PG_USER y FLACSO_PG_PASSWORD.';
            if ($verbose) {
                fwrite(STDERR, $msg . "\n");
            }
            return [
                'ok' => false,
                'error' => $msg,
                'summary' => $output_lines,
            ];
        }

        try {
            $pdo = FLACSO_DB::connection();
        } catch (\Throwable $e) {
            $msg = 'ERROR al conectar con PostgreSQL: ' . $e->getMessage();
            if ($verbose) {
                fwrite(STDERR, $msg . "\n");
            }
            return [
                'ok' => false,
                'error' => $msg,
                'summary' => $output_lines,
            ];
        }
    }

    $log('CONEXION=OK');

    try {
        // Consultar conteos iniciales
        $stmt_offer = $pdo->query('SELECT COUNT(*) FROM offer_inquiries');
        $initial_offer_count = (int) $stmt_offer->fetchColumn();

        $stmt_sem = $pdo->query('SELECT COUNT(*) FROM seminar_inquiries');
        $initial_seminar_count = (int) $stmt_sem->fetchColumn();

        $log("offer_inquiries={$initial_offer_count}");
        $log("seminar_inquiries={$initial_seminar_count}");

        // Iniciar transacción de prueba
        $pdo->beginTransaction();

        $offer_repo = new FLACSO_Offer_Inquiry_Repository();
        $seminar_repo = new FLACSO_Seminar_Inquiry_Repository();

        $diag_offer_cid = 'diag-offer-' . bin2hex(random_bytes(6));
        $diag_seminar_cid = 'diag-seminar-' . bin2hex(random_bytes(6));

        try {
            // 1. Probar INSERT en offer_inquiries
            $offer_res = $offer_repo->insert([
                'consultaId' => $diag_offer_cid,
                'offerWpId'  => 999999,
                'offerName'  => 'Diagnóstico Verificación Conexión',
                'offerType'  => 'Prueba',
                'firstName'  => 'Diagnóstico',
                'lastName'   => 'Verificación',
                'fullName'   => 'Diagnóstico Verificación',
                'email'      => 'diag-offer@flacso.edu.uy',
                'country'    => 'Uruguay',
            ]);

            if (empty($offer_res['id'])) {
                throw new RuntimeException('Fallo al insertar registro diagnóstico en offer_inquiries');
            }

            // 2. Probar UPDATE email status en offer_inquiries
            $offer_repo->update_email_status(
                $diag_offer_cid,
                'sent',
                'test-sender@flacso.edu.uy',
                'diag-mj-offer-1',
                'diag-uuid-offer-1'
            );

            $read_offer = $offer_repo->find_by_consulta_id($diag_offer_cid);
            if (
                !$read_offer
                || ($read_offer['emailStatus'] ?? '') !== 'sent'
                || ($read_offer['mailjetMessageId'] ?? '') !== 'diag-mj-offer-1'
                || ($read_offer['mailjetMessageUuid'] ?? '') !== 'diag-uuid-offer-1'
            ) {
                throw new RuntimeException('Fallo al verificar UPDATE en offer_inquiries dentro de la transacción');
            }

            // 3. Probar INSERT en seminar_inquiries
            $seminar_res = $seminar_repo->insert([
                'consultaId' => $diag_seminar_cid,
                'seminarWpId'=> 999999,
                'seminarName'=> 'Diagnóstico Seminario Conexión',
                'seminarType'=> 'Prueba',
                'firstName'  => 'Diagnóstico',
                'lastName'   => 'Seminario',
                'fullName'   => 'Diagnóstico Seminario',
                'email'      => 'diag-seminar@flacso.edu.uy',
                'country'    => 'Uruguay',
            ]);

            if (empty($seminar_res['id'])) {
                throw new RuntimeException('Fallo al insertar registro diagnóstico en seminar_inquiries');
            }

            // 4. Probar UPDATE email status en seminar_inquiries
            $seminar_repo->update_email_status(
                $diag_seminar_cid,
                'sent',
                'test-sender@flacso.edu.uy',
                'diag-mj-seminar-1',
                'diag-uuid-seminar-1'
            );

            $read_seminar = $seminar_repo->find_by_consulta_id($diag_seminar_cid);
            if (
                !$read_seminar
                || ($read_seminar['emailStatus'] ?? '') !== 'sent'
                || ($read_seminar['mailjetMessageId'] ?? '') !== 'diag-mj-seminar-1'
                || ($read_seminar['mailjetMessageUuid'] ?? '') !== 'diag-uuid-seminar-1'
            ) {
                throw new RuntimeException('Fallo al verificar UPDATE en seminar_inquiries dentro de la transacción');
            }

            // Revertir explícitamente mediante ROLLBACK
            $pdo->rollBack();

        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        // Verificar que los datos diagnósticos no persistieron tras el ROLLBACK
        $stmt_offer_after = $pdo->query('SELECT COUNT(*) FROM offer_inquiries');
        $after_offer_count = (int) $stmt_offer_after->fetchColumn();

        $stmt_sem_after = $pdo->query('SELECT COUNT(*) FROM seminar_inquiries');
        $after_seminar_count = (int) $stmt_sem_after->fetchColumn();

        $persisted_offer = $offer_repo->find_by_consulta_id($diag_offer_cid);
        $persisted_seminar = $seminar_repo->find_by_consulta_id($diag_seminar_cid);

        if ($persisted_offer !== null || $after_offer_count < $initial_offer_count || ($injected_pdo !== null && $after_offer_count !== $initial_offer_count)) {
            throw new RuntimeException("ROLLBACK falló en offer_inquiries: se encontraron registros residuales tras revertir.");
        }

        if ($persisted_seminar !== null || $after_seminar_count < $initial_seminar_count || ($injected_pdo !== null && $after_seminar_count !== $initial_seminar_count)) {
            throw new RuntimeException("ROLLBACK falló en seminar_inquiries: se encontraron registros residuales tras revertir.");
        }

        $log('TRANSACCION_PRUEBA=OK');
        $log('ROLLBACK=OK');
        $log('PERMISOS_ESCRITURA=OK');

        return [
            'ok' => true,
            'conexion' => 'OK',
            'offer_inquiries' => $initial_offer_count,
            'seminar_inquiries' => $initial_seminar_count,
            'transaccion_prueba' => 'OK',
            'rollback' => 'OK',
            'permisos_escritura' => 'OK',
            'summary' => $output_lines,
        ];

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = 'ERROR durante la verificación: ' . $e->getMessage();
        if ($verbose) {
            fwrite(STDERR, $msg . "\n");
        }
        return [
            'ok' => false,
            'error' => $msg,
            'summary' => $output_lines,
        ];
    }
}

// Ejecución directa vía CLI o WP-CLI
$is_direct_cli = (php_sapi_name() === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__);
$is_argv_cli   = (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__);
$is_wp_cli     = defined('WP_CLI') && WP_CLI;

if (($is_direct_cli || $is_argv_cli || $is_wp_cli) && !defined('FLACSO_TEST_MODE')) {
    $result = flacso_run_postgres_verification(null, true);
    exit($result['ok'] ? 0 : 1);
}
