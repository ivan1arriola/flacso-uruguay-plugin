<?php
/**
 * Main Page Module
 * Gestiona la landing page, secciones y bloques de la página principal
 * 
 * @package FLACSO_Uruguay
 * @subpackage Main_Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del módulo
define('FLACSO_MAIN_PAGE_MODULE_PATH', __DIR__ . '/');
define('FLACSO_MAIN_PAGE_MODULE_URL', plugin_dir_url(__FILE__));
define('FLACSO_MAIN_PAGE_VERSION', FLACSO_URUGUAY_VERSION); // Usar la versión del plugin principal

// Cargar clases principales (siempre necesarias)
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-settings.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-blocks.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-loader.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-telegram-manager.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/flacso-consultas.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/flacso-raw-content-api.php';

// Cargar clases de gestión/admin solo en contexto administrativo.
$is_admin_context = is_admin()
    || (function_exists('wp_doing_ajax') && wp_doing_ajax())
    || (defined('REST_REQUEST') && REST_REQUEST);
if ($is_admin_context) {
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-admin.php';
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-unified-settings.php';
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-ajax-settings.php';
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-ajax-handler.php';
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-seminarios.php';
}

// Cargar bloques
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/blocks/listar-paginas/block.php';

// Inicializar módulo
add_action('init', function() {
    // Inicializar clases
    Flacso_Main_Page_Loader::init();
    Flacso_Main_Page_Blocks::init();

    if (class_exists('Flacso_Main_Page_Admin')) {
        Flacso_Main_Page_Admin::init();
    }
    if (class_exists('Flacso_Main_Page_Unified_Settings')) {
        Flacso_Main_Page_Unified_Settings::init();
    }
    if (class_exists('Flacso_AJAX_Settings')) {
        Flacso_AJAX_Settings::init();
    }
    if (class_exists('Flacso_AJAX_Handler')) {
        Flacso_AJAX_Handler::init();
    }
    if (class_exists('Flacso_Main_Page_Seminarios')) {
        Flacso_Main_Page_Seminarios::init();
    }
});

// Inicializar Telegram Manager después de que todos los plugins estén cargados
add_action('plugins_loaded', function() {
    FLACSO_Telegram_Manager::get_instance();
}, 20);
