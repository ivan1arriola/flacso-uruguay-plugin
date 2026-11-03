<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bounded, persistent Meta CAPI queue for ISPConfig.
 * PHP-FPM handles only a database insert; the system cron runs a small worker.
 */
final class FLACSO_Meta_Event_Queue {
    private const SCHEMA_VERSION = '1';
    private const CRON_HOOK = 'flacso_meta_process_batch';
    private const BATCH_SIZE = 20;
    private const MAX_BATCHES = 3;
    private const MAX_ATTEMPTS = 3;

    public static function init(): void {
        add_filter('cron_schedules', [self::class, 'cron_schedules']);
        add_action('init', [self::class, 'ensure_initialized'], 20);
        add_action(self::CRON_HOOK, [self::class, 'process']);
    }

    public static function cron_schedules(array $schedules): array {
        $schedules['flacso_meta_minute'] = [
            'interval' => MINUTE_IN_SECONDS,
            'display' => 'Cada minuto (Meta CAPI)',
        ];
        return $schedules;
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'flacso_meta_queue';
    }

    public static function ensure_initialized(): void {
        if (get_option('flacso_meta_queue_schema') !== self::SCHEMA_VERSION) {
            global $wpdb;
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $table = self::table();
            $charset = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_key char(64) NOT NULL,
                payload longtext NOT NULL,
                test_code varchar(64) NOT NULL DEFAULT '',
                attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
                available_at datetime NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY event_key (event_key),
                KEY available_at (available_at)
            ) {$charset};");
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
                update_option('flacso_meta_queue_schema', self::SCHEMA_VERSION, false);
            }
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'flacso_meta_minute', self::CRON_HOOK);
        }
    }

    public static function enqueue(array $event, string $test_code): bool {
        global $wpdb;
        if (get_option('flacso_meta_queue_schema') !== self::SCHEMA_VERSION) {
            return false;
        }
        if (empty($event['event_id']) || empty($event['event_name'])) {
            return false;
        }

        // A bounded number of rows prevents unlimited disk growth under bot traffic.
        // The rate limiter remains the first line of defence.
        $size = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table());
        if ($size >= 5000) {
            return false;
        }

        $payload = wp_json_encode($event);
        if (!is_string($payload) || $payload === '') {
            return false;
        }
        $result = $wpdb->insert(self::table(), [
            'event_key' => hash('sha256', $event['event_name'] . ':' . $event['event_id']),
            'payload' => $payload,
            'test_code' => substr($test_code, 0, 64),
            'attempts' => 0,
            'available_at' => gmdate('Y-m-d H:i:s'),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], ['%s', '%s', '%s', '%d', '%s', '%s']);

        return $result === 1;
    }

    public static function process(): void {
        global $wpdb;
        $table = self::table();
        // MySQL advisory lock is connection-scoped and avoids stale lock transients.
        $lock_name = substr($wpdb->prefix . 'flacso_meta_worker', 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock_name)) !== 1) {
            return;
        }

        try {
            // Expire stale events rather than retaining personal data indefinitely.
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                gmdate('Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS)
            ));

            for ($batch = 0; $batch < self::MAX_BATCHES; $batch++) {
                $first = $wpdb->get_row(
                    "SELECT test_code FROM {$table} WHERE available_at <= UTC_TIMESTAMP() ORDER BY id ASC LIMIT 1",
                    ARRAY_A
                );
                if (!$first) {
                    break;
                }
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, payload, attempts FROM {$table}
                     WHERE test_code = %s AND available_at <= UTC_TIMESTAMP()
                     ORDER BY id ASC LIMIT %d",
                    $first['test_code'],
                    self::BATCH_SIZE
                ), ARRAY_A);
                if (!$rows) {
                    break;
                }

                $events = [];
                $ids = [];
                foreach ($rows as $row) {
                    $event = json_decode($row['payload'], true);
                    if (is_array($event) && !empty($event['event_name'])) {
                        $events[] = $event;
                    }
                    $ids[] = (int) $row['id'];
                }

                $id_list = implode(',', array_map('intval', $ids));
                if (!$events) {
                    $wpdb->query("DELETE FROM {$table} WHERE id IN ({$id_list})");
                    continue;
                }

                $response = FLACSO_Meta_Tracking::send_queued_capi_batch($events, (string) $first['test_code']);
                $status = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
                if ($status >= 200 && $status < 300) {
                    $wpdb->query("DELETE FROM {$table} WHERE id IN ({$id_list})");
                    continue;
                }

                $permanent = $status >= 400 && $status < 500 && $status !== 429;
                if ($permanent) {
                    $wpdb->query("DELETE FROM {$table} WHERE id IN ({$id_list})");
                } else {
                    // Backoff in minutes (1, 5); discard after 3 attempts.
                    $wpdb->query("UPDATE {$table} SET
                        attempts = attempts + 1,
                        available_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL IF(attempts = 0, 1, 5) MINUTE)
                        WHERE id IN ({$id_list})");
                    $wpdb->query("DELETE FROM {$table} WHERE id IN ({$id_list}) AND attempts >= " . self::MAX_ATTEMPTS);
                }

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[FLACSO Meta Queue] batch failed HTTP ' . $status);
                }
                // A failing Meta endpoint should not generate repeated requests in this tick.
                break;
            }
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }
}
FLACSO_Meta_Event_Queue::init();
