<?php
/**
 * Módulo Consultas - FLACSO Uruguay
 *
 * Persistencia directa en base de datos PostgreSQL y despacho
 * de correos transaccionales a través de Mailjet.
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
    'includes/integrations/class-flacso-mailjet-client.php',
    'modules/consultas/services/class-flacso-offer-inquiry-service.php',
    'modules/consultas/services/class-flacso-seminar-inquiry-service.php',
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

if (class_exists('FLACSO_Consultas_Admin') && function_exists('add_action')) {
    FLACSO_Consultas_Admin::init();
}

