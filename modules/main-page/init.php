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

/**
 * La portada es dueña de la geometría de sus tarjetas de novedades.
 * Se carga después de los estilos base del módulo y usa filemtime para evitar
 * que una versión cacheada mantenga la relación de aspecto anterior.
 */
function flacso_main_page_enqueue_novedades_45_style(): void {
    if (is_admin() || !is_front_page()) {
        return;
    }

    $relative_path = 'assets/css/flacso-novedades-45.css';
    $absolute_path = FLACSO_MAIN_PAGE_MODULE_PATH . $relative_path;
    $version = file_exists($absolute_path)
        ? (string) filemtime($absolute_path)
        : (string) FLACSO_MAIN_PAGE_VERSION;

    wp_enqueue_style(
        'flacso-main-page-novedades-45',
        FLACSO_MAIN_PAGE_MODULE_URL . $relative_path,
        array('flacso-main-page-base'),
        $version
    );
}
add_action('wp_enqueue_scripts', 'flacso_main_page_enqueue_novedades_45_style', 65);

/**
 * La cabecera de Seminarios Próximos no necesita una bajada adicional.
 * Se mantiene el texto fuente por compatibilidad con versiones anteriores del
 * renderer, pero se evita que llegue al HTML público de la portada.
 */
function flacso_main_page_remove_seminarios_proximos_tagline($translated_text, $text, $domain) {
    if (
        'flacso-main-page' === $domain
        && 'Formación intensiva con enfoque práctico' === $text
    ) {
        return '';
    }

    return $translated_text;
}
add_filter('gettext', 'flacso_main_page_remove_seminarios_proximos_tagline', 10, 3);

// Cargar clases principales (siempre necesarias)
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-settings.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-blocks.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-loader.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-migrations.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-main-page-rest-api.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-instagram-api.php';
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
    require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/class-flacso-instagram-post-importer.php';
}

// Cargar bloques
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/blocks/listar-paginas/block.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/blocks/otros-contactos/block.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/blocks/mapa-contacto/block.php';
require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'includes/blocks/contacto-seccion/block.php';

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
    if (class_exists('Flacso_Instagram_Post_Importer')) {
        Flacso_Instagram_Post_Importer::init();
    }
    if (class_exists('Flacso_Main_Page_Migrations')) {
        Flacso_Main_Page_Migrations::init();
    }
});

// Inicializar Telegram Manager después de que todos los plugins estén cargados
add_action('plugins_loaded', function() {
    FLACSO_Telegram_Manager::get_instance();
}, 20);
