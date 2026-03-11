<?php
/**
 * Módulo de Formularios - FLACSO Uruguay
 * Integración de Formulario de Consultas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del módulo formularios
if (!defined('FC_PLUGIN_VERSION')) {
    define('FC_PLUGIN_VERSION', FLACSO_URUGUAY_VERSION);
}

if (!defined('FC_PLUGIN_URL')) {
    define('FC_PLUGIN_URL', FLACSO_URUGUAY_URL . 'modules/formularios/');
}

if (!defined('FC_PLUGIN_DIR')) {
    define('FC_PLUGIN_DIR', FLACSO_URUGUAY_PATH . 'modules/formularios/');
}

// Cargar assets
flacso_safe_require('modules/formularios/includes/assets.php');

// Cargar configuración y handlers de formularios
flacso_safe_require('modules/formularios/includes/cpt.php');
flacso_safe_require('modules/formularios/includes/helpers.php');
flacso_safe_require('modules/formularios/includes/settings.php');
flacso_safe_require('modules/formularios/includes/validation.php');
flacso_safe_require('modules/formularios/includes/telegram.php');
flacso_safe_require('modules/formularios/includes/gmail.php');
flacso_safe_require('modules/formularios/includes/form-handlers.php');
flacso_safe_require('modules/formularios/includes/confirmacion-consulta.php');
flacso_safe_require('modules/formularios/includes/form-render.php');

// Inicializar
add_action('plugins_loaded', function() {
    // El módulo se inicializa mediante hooks de assets y bloques
}, 10);
