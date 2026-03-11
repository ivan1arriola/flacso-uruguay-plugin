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

// Cargar helpers y clases
flacso_safe_require('modules/seminarios/includes/helpers.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-cpt.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-taxonomies.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-meta.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-seeder.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-admin.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-rest-api.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-templates.php');
flacso_safe_require('modules/seminarios/includes/class-seminario-docentes.php');

// Cargar bloques
flacso_safe_require('modules/seminarios/blocks/seminarios-lista/render.php');

// Inicializar módulo
class Seminario_Plugin {
    public function __construct() {
        add_action('init', ['Seminario_CPT', 'register']);
        add_action('init', ['Seminario_Taxonomies', 'register']);
        add_action('init', ['Seminario_Taxonomies', 'register_term_meta']);
        add_action('init', ['Seminario_Meta', 'register']);
        add_action('init', [$this, 'register_blocks']);
        add_action('after_setup_theme', function() {
            add_theme_support('post-thumbnails', ['seminario']);
        });

        add_action('add_meta_boxes', ['Seminario_Admin', 'add_meta_boxes']);
        add_action('admin_enqueue_scripts', ['Seminario_Admin', 'enqueue_admin_assets']);
        add_action('save_post_seminario', ['Seminario_Admin', 'save_meta']);
        add_action('wp_ajax_flacso_seminario_search_docentes', ['Seminario_Admin', 'search_docentes']);

        add_filter('manage_seminario_posts_columns', ['Seminario_Admin', 'add_list_columns']);
        add_filter('manage_edit-seminario_sortable_columns', ['Seminario_Admin', 'make_list_columns_sortable']);
        add_action('manage_seminario_posts_custom_column', ['Seminario_Admin', 'render_list_columns'], 10, 2);
        add_action('pre_get_posts', ['Seminario_Admin', 'handle_sortable_columns']);

        add_action('admin_menu', ['Seminario_Admin', 'register_menu']);

        add_filter('single_template', ['Seminario_Templates', 'single_template']);
        add_filter('template_include', ['Seminario_Templates', 'seminarios_template'], 10);
        add_filter('template_include', ['Seminario_Templates', 'consulta_template'], 11);
        add_action('wp_enqueue_scripts', ['Seminario_Templates', 'enqueue_public_assets']);

        add_action('init', ['Seminario_Templates', 'register_preinscripcion_route']);
        add_filter('query_vars', ['Seminario_Templates', 'add_query_vars']);
        add_filter('template_include', ['Seminario_Templates', 'preinscripcion_template'], 9);

        add_action('rest_api_init', ['Seminario_REST_API', 'register_routes']);
    }

    public function register_blocks() {
        // Registrar bloque Seminarios Lista
        $block_path = dirname(__DIR__) . '/seminarios/blocks/seminarios-lista';
        
        if (file_exists($block_path . '/block.json')) {
            register_block_type($block_path, [
                'render_callback' => 'flacso_render_seminarios_lista_block'
            ]);

            // Registrar assets del bloque
            wp_register_script(
                'flacso-seminarios-lista-editor',
                plugins_url('modules/seminarios/blocks/seminarios-lista/editor.js', FLACSO_URUGUAY_FILE),
                ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
                FLACSO_SEMINARIO_VERSION,
                true
            );

            wp_register_style(
                'flacso-seminarios-lista',
                plugins_url('modules/seminarios/blocks/seminarios-lista/style.css', FLACSO_URUGUAY_FILE),
                [],
                FLACSO_SEMINARIO_VERSION
            );
        }
    }

    public static function activate() {
        Seminario_CPT::register();
        Seminario_Taxonomies::register();
        Seminario_Taxonomies::register_term_meta();
        Seminario_Meta::register();
        if (class_exists('Seminario_Seeder')) {
            Seminario_Seeder::seed();
        }
        Seminario_Templates::register_preinscripcion_route();
        flush_rewrite_rules();
    }
}

// Inicializar módulo inmediatamente (no esperar a plugins_loaded)
static $seminario_plugin = null;
if ($seminario_plugin === null) {
    $seminario_plugin = new Seminario_Plugin();
}
