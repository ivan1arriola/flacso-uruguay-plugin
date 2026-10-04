<?php
/**
 * Contrato del disparador WP-Cron para la cola transaccional.
 */

$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

$GLOBALS['delivery_cron_actions'] = [];
$GLOBALS['delivery_cron_filters'] = [];
$GLOBALS['delivery_cron_events'] = [];

function delivery_cron_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void {
    $GLOBALS['delivery_cron_actions'][$hook] = $callback;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void {
    $GLOBALS['delivery_cron_filters'][$hook] = $callback;
}

function wp_next_scheduled($hook, $args = []) {
    return $GLOBALS['delivery_cron_events'][$hook]['timestamp'] ?? false;
}

function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
    $GLOBALS['delivery_cron_events'][$hook] = [
        'timestamp'  => $timestamp,
        'recurrence' => $recurrence,
        'args'       => $args,
    ];
    return true;
}

require_once $root . '/modules/consultas/services/class-flacso-inquiry-delivery-worker.php';

FLACSO_Inquiry_Delivery_Worker::init();

$hook = FLACSO_Inquiry_Delivery_Worker::CRON_HOOK;
delivery_cron_assert(isset($GLOBALS['delivery_cron_actions'][$hook]), 'el worker debe registrar su hook WP-Cron');
delivery_cron_assert(isset($GLOBALS['delivery_cron_filters']['cron_schedules']), 'el worker debe registrar su intervalo de un minuto');
delivery_cron_assert(isset($GLOBALS['delivery_cron_events'][$hook]), 'el worker debe programar el evento si no existe');
delivery_cron_assert($GLOBALS['delivery_cron_events'][$hook]['recurrence'] === 'flacso_delivery_minutely', 'la cola debe ejecutarse cada minuto');

$schedules = call_user_func($GLOBALS['delivery_cron_filters']['cron_schedules'], []);
delivery_cron_assert(($schedules['flacso_delivery_minutely']['interval'] ?? 0) === 60, 'el intervalo de la cola debe ser de 60 segundos');

echo "OK inquiry-delivery-cron-test\n";
