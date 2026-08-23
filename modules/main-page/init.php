<?php
/**
 * Main Page Module
 *
 * Compone la portada y expone su configuración. Los datos e integraciones de
 * cada dominio permanecen en sus módulos.
 *
 * @package FLACSO_Uruguay
 * @subpackage Main_Page
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_MAIN_PAGE_MODULE_PATH')) {
    define('FLACSO_MAIN_PAGE_MODULE_PATH', __DIR__ . '/');
}
if (!defined('FLACSO_MAIN_PAGE_MODULE_URL')) {
    define('FLACSO_MAIN_PAGE_MODULE_URL', plugin_dir_url(__FILE__));
}
if (!defined('FLACSO_MAIN_PAGE_VERSION')) {
    define('FLACSO_MAIN_PAGE_VERSION', FLACSO_URUGUAY_VERSION);
}

require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-section-keys.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-settings.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-settings-migration.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-homepage-section-registry.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-blocks.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-loader.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-migrations.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-rest-api.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/flacso-raw-content-api.php';

$is_admin_context = is_admin()
    || (function_exists('wp_doing_ajax') && wp_doing_ajax())
    || (defined('REST_REQUEST') && REST_REQUEST);

if ($is_admin_context) {
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-editor-bridge.php';
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-admin.php';
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-ajax-settings.php';
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-ajax-handler.php';
}

require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/blocks/listar-paginas/block.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/blocks/otros-contactos/block.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/blocks/mapa-contacto/block.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/blocks/contacto-seccion/block.php';

Flacso_Main_Page_Settings_Migration::init();

add_action('init', static function (): void {
    Flacso_Main_Page_Loader::init();
    Flacso_Main_Page_Blocks::init();

    foreach ([
        'Flacso_Main_Page_Admin',
        'Flacso_Main_Page_Unified_Settings',
        'Flacso_AJAX_Settings',
        'Flacso_AJAX_Handler',
        'Flacso_Main_Page_Migrations',
    ] as $class_name) {
        if (class_exists($class_name) && method_exists($class_name, 'init')) {
            $class_name::init();
        }
    }
});
