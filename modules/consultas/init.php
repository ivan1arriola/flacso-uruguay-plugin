<?php
/**
 * Módulo Consultas - FLACSO Uruguay.
 *
 * Persistencia PostgreSQL, snapshots inmutables, cola transaccional Mautic y
 * sincronización comercial separada por consentimiento.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_CONSULTAS_MODULE_PATH')) {
    define('FLACSO_CONSULTAS_MODULE_PATH', __DIR__ . '/');
}

if (!defined('FLACSO_CONSULTAS_MODULE_VERSION')) {
    define('FLACSO_CONSULTAS_MODULE_VERSION', defined('FLACSO_URUGUAY_VERSION') ? FLACSO_URUGUAY_VERSION : '7.0.0');
}

$flacso_consultas_files = [
    'includes/database/class-flacso-db.php',
    'includes/database/repositories/class-flacso-base-inquiry-repository.php',
    'includes/database/repositories/class-flacso-offer-inquiry-repository.php',
    'includes/database/repositories/class-flacso-seminar-inquiry-repository.php',
    'includes/database/repositories/class-flacso-inquiry-analytics-repository.php',
    'includes/database/repositories/class-flacso-inquiry-delivery-repository.php',

    // Mailjet se conserva únicamente mientras exista código legado que deba
    // retirarse después del piloto; el flujo nuevo no lo invoca.
    'includes/integrations/class-flacso-mailjet-client.php',
    'includes/integrations/class-flacso-mautic-client.php',

    'modules/consultas/services/class-flacso-inquiry-tag-factory.php',
    'modules/consultas/services/class-flacso-inquiry-snapshot.php',
    'modules/consultas/services/class-flacso-inquiry-context-service.php',
    'modules/consultas/services/class-flacso-mautic-contract-manifest.php',
    'modules/consultas/services/class-flacso-mautic-contract-validator.php',
    'modules/consultas/services/class-flacso-mautic-payload-builder.php',
    'modules/consultas/services/class-flacso-inquiry-marketing-service.php',
    'modules/consultas/services/class-flacso-inquiry-delivery-service.php',
    'modules/consultas/services/class-flacso-inquiry-delivery-worker.php',

    'modules/consultas/services/class-flacso-offer-inquiry-service.php',
    'modules/consultas/services/class-flacso-seminar-inquiry-service.php',
    'modules/consultas/services/class-flacso-inquiry-followup-service.php',

    'modules/consultas/includes/class-flacso-consultas-cli.php',
    'modules/consultas/includes/class-flacso-consultas-admin.php',
];

$flacso_base_dir = defined('FLACSO_URUGUAY_PATH') ? FLACSO_URUGUAY_PATH : dirname(__DIR__, 2) . '/';

foreach ($flacso_consultas_files as $flacso_file) {
    if (function_exists('flacso_safe_require')) {
        flacso_safe_require($flacso_file);
    } else {
        require_once rtrim($flacso_base_dir, '/') . '/' . ltrim($flacso_file, '/');
    }
}

// No registrar WP-Cron: la cola está diseñada para cron de servidor + WP-CLI.
if (class_exists('FLACSO_Consultas_CLI')) {
    FLACSO_Consultas_CLI::register();
}

if (class_exists('FLACSO_Consultas_Admin') && function_exists('add_action')) {
    FLACSO_Consultas_Admin::init();
}
