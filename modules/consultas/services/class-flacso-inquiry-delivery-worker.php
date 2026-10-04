<?php
/**
 * Worker de la cola transaccional de consultas.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_Inquiry_Delivery_Repository')) {
    require_once dirname(__DIR__, 3) . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';
}
if (!class_exists('FLACSO_Inquiry_Delivery_Service')) {
    require_once __DIR__ . '/class-flacso-inquiry-delivery-service.php';
}

final class FLACSO_Inquiry_Delivery_Worker {
    public const OPTION_ENABLED = 'flacso_inquiry_delivery_queue_enabled';
    public const CRON_HOOK = 'flacso_inquiry_delivery_cron';
    public const MAX_BATCH_SIZE = 1;
    private const LOCK_OPTION = 'flacso_inquiry_delivery_worker_lock';
    private const LOCK_SECONDS = 180;

    private static ?FLACSO_Inquiry_Delivery_Repository $repository = null;
    private static bool $process_lock = false;

    public static function set_repository(?FLACSO_Inquiry_Delivery_Repository $repository): void {
        self::$repository = $repository;
    }

    public static function init(): void {
        if (function_exists('add_action')) {
            add_action(self::CRON_HOOK, [self::class, 'run_cron']);
        }

        if (function_exists('add_filter')) {
            add_filter('cron_schedules', [self::class, 'cron_schedules']);
        }

        if (function_exists('wp_next_scheduled') && !wp_next_scheduled(self::CRON_HOOK)
            && function_exists('wp_schedule_event')) {
            wp_schedule_event(time() + 60, 'flacso_delivery_minutely', self::CRON_HOOK);
        }
    }

    public static function cron_schedules(array $schedules): array {
        $schedules['flacso_delivery_minutely'] = [
            'interval' => 60,
            'display'  => 'FLACSO delivery queue every minute',
        ];
        return $schedules;
    }

    public static function run_cron(): void {
        self::run(10);
    }

    public static function run(int $limit = self::MAX_BATCH_SIZE): array {
        $enabled = function_exists('get_option')
            ? (string) get_option(self::OPTION_ENABLED, '0') === '1'
            : false;
        if (!$enabled) {
            return ['ok' => true, 'status' => 'disabled', 'processed' => 0, 'results' => []];
        }

        $token = self::acquire_lock();
        if ($token === null) {
            return ['ok' => true, 'status' => 'locked', 'processed' => 0, 'results' => []];
        }

        try {
            $repository = self::$repository ?? new FLACSO_Inquiry_Delivery_Repository();
            FLACSO_Inquiry_Delivery_Service::set_repository($repository);

            $claimed = $repository->claim_pending_batch(
                min(self::MAX_BATCH_SIZE, max(1, $limit)),
                120
            );
            $results = [];
            foreach ($claimed as $delivery) {
                $id = (string) ($delivery['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $results[$id] = FLACSO_Inquiry_Delivery_Service::process($id);
            }

            return [
                'ok' => true,
                'status' => 'completed',
                'processed' => count($results),
                'results' => $results,
            ];
        } finally {
            self::release_lock($token);
        }
    }

    public static function retention(int $days = 90): array {
        $repository = self::$repository ?? new FLACSO_Inquiry_Delivery_Repository();
        $count = $repository->anonymize_due_deliveries(max(1, $days));
        return ['ok' => true, 'status' => 'completed', 'anonymized' => $count];
    }

    private static function acquire_lock(): ?string {
        if (self::$process_lock) {
            return null;
        }

        $token = bin2hex(random_bytes(16));
        $expires = time() + self::LOCK_SECONDS;

        if (function_exists('add_option') && function_exists('get_option')) {
            $payload = json_encode(['token' => $token, 'expires' => $expires]);
            if (add_option(self::LOCK_OPTION, $payload, '', 'no')) {
                self::$process_lock = true;
                return $token;
            }

            $existing = json_decode((string) get_option(self::LOCK_OPTION, ''), true);
            $expired = !is_array($existing) || (int) ($existing['expires'] ?? 0) < time();
            if (!$expired) {
                return null;
            }

            if (function_exists('update_option')) {
                update_option(self::LOCK_OPTION, $payload, false);
                self::$process_lock = true;
                return $token;
            }
            return null;
        }

        self::$process_lock = true;
        return $token;
    }

    private static function release_lock(string $token): void {
        if (function_exists('get_option') && function_exists('delete_option')) {
            $existing = json_decode((string) get_option(self::LOCK_OPTION, ''), true);
            if (is_array($existing) && hash_equals((string) ($existing['token'] ?? ''), $token)) {
                delete_option(self::LOCK_OPTION);
            }
        }
        self::$process_lock = false;
    }
}
