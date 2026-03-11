<?php
/**
 * Plugin Name: FLACSO Uruguay - Plataforma Integrada
 * Plugin URI: https://flacso.edu.uy
 * Description: Plataforma integrada de FLACSO Uruguay con gestión de docentes, seminarios, eventos, oferta académica y formularios. Consolida múltiples plugins en una arquitectura modular.
 * Version: 1.1.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: FLACSO Uruguay
 * Author URI: https://flacso.edu.uy
 * Text Domain: flacso-uruguay
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// Constantes Globales
// ============================================
define('FLACSO_URUGUAY_VERSION', '1.1.1');
define('FLACSO_URUGUAY_FILE', __FILE__);
define('FLACSO_URUGUAY_PATH', plugin_dir_path(__FILE__));
define('FLACSO_URUGUAY_URL', plugin_dir_url(__FILE__));

// Compatibilidad con plugins antiguos
define('CPT_DOCENTES_VERSION', FLACSO_URUGUAY_VERSION);
define('CPT_DOCENTES_PATH', FLACSO_URUGUAY_PATH);
define('CPT_DOCENTES_URL', FLACSO_URUGUAY_URL);

define('FLACSO_SEMINARIO_VERSION', FLACSO_URUGUAY_VERSION);
// En el plugin unificado, los assets y templates de seminarios
// viven dentro del módulo `modules/seminarios/`, no en la raíz.
// Ajustamos las constantes de compatibilidad para que apunten ahí,
// de modo que `Seminario_Templates` encuentre correctamente
// `templates/single-seminario.php`, `seminarios-listado.php`, etc.
define('FLACSO_SEMINARIO_PATH', FLACSO_URUGUAY_PATH . 'modules/seminarios/');
define('FLACSO_SEMINARIO_URL', FLACSO_URUGUAY_URL . 'modules/seminarios/');

define('CPT_EVENTOS_VERSION', FLACSO_URUGUAY_VERSION);
define('CPT_EVENTOS_PATH', FLACSO_URUGUAY_PATH);
define('CPT_EVENTOS_URL', FLACSO_URUGUAY_URL);

define('FLACSO_OFERTA_ACADEMICA_VERSION', FLACSO_URUGUAY_VERSION);
define('FLACSO_OFERTA_ACADEMICA_PATH', FLACSO_URUGUAY_PATH);
define('FLACSO_OFERTA_ACADEMICA_URL', FLACSO_URUGUAY_URL);

define('FLACSO_POSGRADOS_SLUG', 'flacso-posgrados-docentes');
define('FLACSO_POSGRADOS_PLUGIN_PATH', FLACSO_URUGUAY_PATH);

// ============================================
// Configuracion de actualizaciones desde GitHub
// ============================================
// Se puede definir en wp-config.php o en un mu-plugin.
if (!defined('FLACSO_URUGUAY_UPDATE_REPO')) {
    define('FLACSO_URUGUAY_UPDATE_REPO', 'https://github.com/ivan1arriola/flacso-uruguay-plugin/');
}

if (!defined('FLACSO_URUGUAY_UPDATE_BRANCH')) {
    define('FLACSO_URUGUAY_UPDATE_BRANCH', 'main');
}

if (!defined('FLACSO_URUGUAY_GITHUB_TOKEN')) {
    define('FLACSO_URUGUAY_GITHUB_TOKEN', '');
}

// ============================================
// Carga de funciones principales
// ============================================
require_once FLACSO_URUGUAY_PATH . 'includes/core/helpers.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/loader.php';

if (!function_exists('flacso_uruguay_setup_update_checker')) {
    /**
     * Configura Plugin Update Checker para actualizaciones desde GitHub.
     *
     * Requisitos:
     * - Definir FLACSO_URUGUAY_UPDATE_REPO con la URL del repo GitHub.
     * - Incluir la libreria en /plugin-update-checker o via Composer.
     */
    function flacso_uruguay_setup_update_checker() {
        $repo_url = trim((string) FLACSO_URUGUAY_UPDATE_REPO);
        if ($repo_url === '') {
            return;
        }

        $puc_file = FLACSO_URUGUAY_PATH . 'plugin-update-checker/plugin-update-checker.php';
        $autoload_file = FLACSO_URUGUAY_PATH . 'vendor/autoload.php';

        if (file_exists($puc_file)) {
            require_once $puc_file;
        } elseif (file_exists($autoload_file)) {
            require_once $autoload_file;
        }

        if (!class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
            return;
        }

        $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            $repo_url,
            FLACSO_URUGUAY_FILE,
            'flacso-uruguay'
        );

        $branch = trim((string) FLACSO_URUGUAY_UPDATE_BRANCH);
        if ($branch === '') {
            $branch = 'main';
        }
        $update_checker->setBranch($branch);

        if (method_exists($update_checker, 'getVcsApi')) {
            $vcs_api = $update_checker->getVcsApi();
            if (is_object($vcs_api) && method_exists($vcs_api, 'enableReleaseAssets')) {
                $vcs_api->enableReleaseAssets();
            }
        }

        $token = trim((string) FLACSO_URUGUAY_GITHUB_TOKEN);
        if ($token !== '') {
            $update_checker->setAuthentication($token);
        }
    }
}

add_action('plugins_loaded', 'flacso_uruguay_setup_update_checker', 20);

// ============================================
// Inicialización del Plugin
// ============================================
class FLACSO_Uruguay_Plugin {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Cargar módulos
        add_action('plugins_loaded', [$this, 'load_modules'], 10);
        
        // Cargar idiomas
        add_action('plugins_loaded', [$this, 'load_textdomain'], 5);
        
        // Registrar categorías de bloques
        add_filter('block_categories_all', [$this, 'register_block_categories'], 10, 2);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'flacso-uruguay',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    public function register_block_categories($categories, $context) {
        // Obtener slugs existentes para evitar duplicados
        $existing_slugs = wp_list_pluck($categories, 'slug');
        
        // Registrar categoría principal de FLACSO Uruguay
        if (!in_array('flacso-uruguay', $existing_slugs, true)) {
            array_unshift($categories, [
                'slug'  => 'flacso-uruguay',
                'title' => __('FLACSO Uruguay', 'flacso-uruguay'),
                'icon'  => null
            ]);
        }
        
        return $categories;
    }
    
    public function load_modules() {
        $loader = FLACSO_Uruguay_Loader::instance();
        
        // Cargar módulos en orden de dependencias
        $loader->load_module('core');      // Funciones base
        $loader->load_module('docentes');  // CPT Docentes
        $loader->load_module('seminarios'); // CPT Seminarios
        $loader->load_module('eventos');    // CPT Eventos
        $loader->load_module('oferta-academica'); // Oferta Académica
        $loader->load_module('formularios'); // Formularios
        $loader->load_module('posgrados');  // Posgrados
        $loader->load_module('shortcodes'); // Shortcodes
        $loader->load_module('main-page');  // Landing Page y Secciones
        $loader->load_module('preinscripcion'); // Formularios de Preinscripción
    }
    
    public static function activate() {
        // Lógica de activación
        flush_rewrite_rules();
    }
    
    public static function deactivate() {
        // Lógica de desactivación
        flush_rewrite_rules();
    }
}

// Inicializar el plugin
FLACSO_Uruguay_Plugin::instance();

// Hooks de activación/desactivación
register_activation_hook(__FILE__, ['FLACSO_Uruguay_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['FLACSO_Uruguay_Plugin', 'deactivate']);
