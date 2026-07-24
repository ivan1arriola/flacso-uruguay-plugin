<?php
/**
 * Modulo de Charlas Abiertas - FLACSO Uruguay
 * Integracion de CPT, bloque Gutenberg y endpoints REST.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Si el plugin standalone esta activo, se evita carga duplicada.
if ((defined('FLACSO_CHARLAS_ABIERTAS_FILE') && FLACSO_CHARLAS_ABIERTAS_FILE !== __FILE__) || function_exists('flacso_charlas_abiertas_register_cpt')) {
    return;
}

if (!defined('FLACSO_CHARLAS_ABIERTAS_VERSION')) {
    define('FLACSO_CHARLAS_ABIERTAS_VERSION', FLACSO_URUGUAY_VERSION);
}
if (!defined('FLACSO_CHARLAS_ABIERTAS_FILE')) {
    define('FLACSO_CHARLAS_ABIERTAS_FILE', __FILE__);
}
if (!defined('FLACSO_CHARLAS_ABIERTAS_PATH')) {
    define('FLACSO_CHARLAS_ABIERTAS_PATH', FLACSO_URUGUAY_PATH . 'modules/charlas-abiertas/');
}
if (!defined('FLACSO_CHARLAS_ABIERTAS_URL')) {
    define('FLACSO_CHARLAS_ABIERTAS_URL', FLACSO_URUGUAY_URL . 'modules/charlas-abiertas/');
}

// El formulario ya no tiene un CPT propio: sus campos viven en el CPT `evento`.
// El archivo legacy se conserva en el paquete únicamente para poder reconocer y
// migrar instalaciones anteriores.
flacso_safe_require('modules/charlas-abiertas/includes/unified-events.php');
flacso_safe_require('modules/charlas-abiertas/includes/frontend-assets.php');
flacso_safe_require('modules/charlas-abiertas/includes/block.php');
flacso_safe_require('modules/charlas-abiertas/includes/rest.php');
flacso_safe_require('modules/charlas-abiertas/includes/settings.php');
