<?php
/**
 * Comandos WP-CLI de la cola transaccional.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

final class FLACSO_Consultas_CLI {
    public static function register(): void {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('WP_CLI')) {
            return;
        }

        WP_CLI::add_command('flacso consultas deliveries run', [self::class, 'run']);
        WP_CLI::add_command('flacso consultas deliveries retention', [self::class, 'retention']);
        WP_CLI::add_command('flacso consultas deliveries reconcile', [self::class, 'reconcile']);
    }

    public static function run(array $args, array $assoc_args): void {
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 10;
        $result = FLACSO_Inquiry_Delivery_Worker::run($limit);
        self::output($result);
    }

    public static function retention(array $args, array $assoc_args): void {
        $days = isset($assoc_args['days']) ? (int) $assoc_args['days'] : 90;
        self::output(FLACSO_Inquiry_Delivery_Worker::retention($days));
    }

    /**
     * Sólo lista entregas inciertas. No reenvía automáticamente.
     * --delivery=<id> --confirm-failed se reserva para una conciliación
     * operativa explícita futura; no existe una acción destructiva aquí.
     */
    public static function reconcile(array $args, array $assoc_args): void {
        $repository = new FLACSO_Inquiry_Delivery_Repository();
        $rows = $repository->find_for_reconciliation(100);

        if (!empty($assoc_args['delivery']) && empty($assoc_args['confirm-failed'])) {
            WP_CLI::error('La conciliación de una entrega específica requiere --confirm-failed y una verificación manual previa; este comando no reenvía.');
            return;
        }

        self::output([
            'ok' => true,
            'status' => 'manual_reconciliation_required',
            'count' => count($rows),
            'delivery_ids' => array_values(array_filter(array_map(
                static fn(array $row): string => (string) ($row['id'] ?? ''),
                $rows
            ))),
        ]);
    }

    private static function output(array $result): void {
        $json = function_exists('wp_json_encode')
            ? wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        WP_CLI::line((string) $json);
    }
}
