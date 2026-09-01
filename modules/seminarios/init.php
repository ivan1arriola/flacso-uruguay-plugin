<?php
/**
 * Módulo de Seminarios - FLACSO Uruguay
 * Integración de CPT Seminario
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del módulo
if (!defined('FLACSO_SEMINARIO_PATH')) {
    define('FLACSO_SEMINARIO_PATH', __DIR__ . '/');
}
if (!defined('FLACSO_SEMINARIO_URL')) {
    define('FLACSO_SEMINARIO_URL', plugin_dir_url(__FILE__));
}
if (!defined('FLACSO_SEMINARIO_VERSION')) {
    define('FLACSO_SEMINARIO_VERSION', FLACSO_URUGUAY_VERSION);
}

// Modelo final: Seminario y Edicion son entidades independientes.
flacso_safe_require('modules/seminarios/includes/class-seminario-cpt.php');
flacso_safe_require('modules/seminarios/includes/class-seminario.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-integrado.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-admin-fields.php');
flacso_safe_require('modules/seminarios/includes/class-edicion.php');
flacso_safe_require('modules/seminarios/includes/class-edicion-admin-fields.php');

// Inicializar módulo
class Seminario_Plugin {
    public function __construct() {
        add_action('init', ['Seminario_CPT', 'register'], 4);
        add_action('init', ['FLACSO_Seminario', 'register_meta'], 5);
        add_action('init', ['FLACSO_Edicion', 'register'], 5);
        FLACSO_Seminario_Admin_Fields::init();
        FLACSO_Edicion_Admin_Fields::init();
        add_action('after_setup_theme', function() {
            add_theme_support('post-thumbnails', ['seminario']);
        });

    }

    public static function activate() {
        Seminario_CPT::register();
        FLACSO_Seminario::register_meta();
        FLACSO_Edicion::register();
        flush_rewrite_rules();
    }
}

// Inicializar módulo inmediatamente (no esperar a plugins_loaded)
static $seminario_plugin = null;
if ($seminario_plugin === null) {
    $seminario_plugin = new Seminario_Plugin();
}
