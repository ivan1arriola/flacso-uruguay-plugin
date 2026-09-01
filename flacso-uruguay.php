<?php
/**
 * Plugin Name: FLACSO Uruguay - Plataforma Integrada
 * Plugin URI: https://flacso.edu.uy
 * Description: Plataforma integrada de FLACSO Uruguay con gestion de docentes, seminarios, eventos, oferta academica y formularios. Consolida multiples plugins en una arquitectura modular.
 * Version: 7.0.0
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
define('FLACSO_URUGUAY_VERSION', '7.0.0');
define('FLACSO_URUGUAY_FILE', __FILE__);
define('FLACSO_URUGUAY_PATH', plugin_dir_path(__FILE__));
define('FLACSO_URUGUAY_URL', plugin_dir_url(__FILE__));

define('CPT_DOCENTES_VERSION', FLACSO_URUGUAY_VERSION);
define('CPT_DOCENTES_PATH', FLACSO_URUGUAY_PATH);
define('CPT_DOCENTES_URL', FLACSO_URUGUAY_URL);

define('FLACSO_SEMINARIO_VERSION', FLACSO_URUGUAY_VERSION);
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
// Carga de funciones principales
// ============================================
require_once FLACSO_URUGUAY_PATH . 'includes/core/helpers.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-rest-visibility.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-rest-dto-loader.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-editor-admin-mode.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-integrations-settings.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-admin-panel.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-meta-tracking.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/class-flacso-meta-leads-webhook.php';
require_once FLACSO_URUGUAY_PATH . 'includes/core/loader.php';

// ============================================
// Inicializacion del Plugin
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
        // Cargar modulos
        add_action('plugins_loaded', [$this, 'load_modules'], 10);
        
        // Cargar idiomas
        add_action('plugins_loaded', [$this, 'load_textdomain'], 5);
        
        // Registrar categorias de bloques
        add_filter('block_categories_all', [$this, 'register_block_categories'], 10, 2);

        // Ayuda a precalentar dominios externos usados frecuentemente por el sitio.
        add_filter('wp_resource_hints', [$this, 'add_resource_hints'], 10, 2);
        
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
        
        // Registrar categoria principal de FLACSO Uruguay
        if (!in_array('flacso-uruguay', $existing_slugs, true)) {
            array_unshift($categories, [
                'slug'  => 'flacso-uruguay',
                'title' => __('FLACSO Uruguay', 'flacso-uruguay'),
                'icon'  => null
            ]);
        }
        
        return $categories;
    }

    public function add_resource_hints($hints, $relation_type) {
        if ('preconnect' !== $relation_type) {
            return $hints;
        }

        $hints[] = 'https://fonts.googleapis.com';
        $hints[] = [
            'href' => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        ];
        $hints[] = [
            'href' => 'https://cdn.jsdelivr.net',
            'crossorigin' => 'anonymous',
        ];

        return $hints;
    }
    
    public function load_modules() {
        $loader = FLACSO_Uruguay_Loader::instance();
        
        // Cargar modulos en orden de dependencias
        $loader->load_module('core');      // Funciones base
        $loader->load_module('docentes');  // CPT Docentes
        $loader->load_module('autoridades'); // Autoridades FLACSO
        $loader->load_module('seminarios'); // CPT Seminarios
        $loader->load_module('eventos');    // CPT Eventos
        $loader->load_module('convenios');  // CPT Convenios y migracion desde entradas
        $loader->load_module('oferta-academica'); // Oferta Academica
        $loader->load_module('formularios'); // Formularios
        $loader->load_module('charlas-abiertas'); // Charlas Abiertas
        $loader->load_module('posgrados');  // Posgrados
        $loader->load_module('shortcodes'); // Shortcodes
        $loader->load_module('mailing'); // Suscripciones al mailing
        $loader->load_module('preguntas-frecuentes'); // FAQ administrables
        $loader->load_module('main-page');  // Landing Page y Secciones
    }
    
    public static function activate() {
        // Logica de activacion
        flush_rewrite_rules();
    }
    
    public static function deactivate() {
        // Logica de desactivacion
        flush_rewrite_rules();
    }
}

// Inicializar el plugin
FLACSO_Uruguay_Plugin::instance();

// Hooks de activacion/desactivacion
register_activation_hook(__FILE__, ['FLACSO_Uruguay_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['FLACSO_Uruguay_Plugin', 'deactivate']);
