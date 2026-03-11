<?php
/**
 * Módulo de Posgrados - FLACSO Uruguay
 * Integración de Posgrados y Docentes
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del módulo posgrados
if (!defined('FLACSO_POSGRADOS_PLUGIN_URL')) {
    define('FLACSO_POSGRADOS_PLUGIN_URL', FLACSO_URUGUAY_URL . 'modules/posgrados/');
}

if (!defined('FLACSO_POSGRADOS_PLUGIN_PATH')) {
    define('FLACSO_POSGRADOS_PLUGIN_PATH', FLACSO_URUGUAY_PATH . 'modules/posgrados/');
}

// Cargar clases principales
flacso_safe_require('modules/posgrados/includes/class-flacso-posgrados-plugin.php');
flacso_safe_require('modules/posgrados/includes/class-flacso-posgrados-pages.php');
flacso_safe_require('modules/posgrados/includes/class-flacso-posgrados-fields.php');
flacso_safe_require('modules/posgrados/includes/class-flacso-posgrados-consultas-form.php');
flacso_safe_require('modules/posgrados/includes/class-flacso-posgrados-docentes-sync.php');
flacso_safe_require('modules/posgrados/includes/rest-api-posgrados.php');

// Cargar módulo interno de docentes solo como fallback.
if (
    !defined('FLACSO_MAIN_DOCENTES_MODULE_LOADED')
    && !class_exists('CPT_Docente')
    && !function_exists('dp_docentes_register_assets')
) {
    flacso_safe_require('modules/posgrados/docentes/docentes-plugin.php');
}

// Inicializar
add_action('plugins_loaded', function() {
    if (class_exists('FLACSO_Posgrados_Plugin')) {
        FLACSO_Posgrados_Plugin::init();
    }
}, 10);
