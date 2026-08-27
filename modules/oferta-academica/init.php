<?php
/**
 * Módulo de Oferta Académica - FLACSO Uruguay
 * Integración de Oferta Académica
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del módulo
if (!defined('FLACSO_OFERTA_ACADEMICA_PATH')) {
    define('FLACSO_OFERTA_ACADEMICA_PATH', __DIR__ . '/');
}
if (!defined('FLACSO_OFERTA_ACADEMICA_URL')) {
    define('FLACSO_OFERTA_ACADEMICA_URL', plugin_dir_url(__FILE__));
}
if (!defined('FLACSO_OFERTA_ACADEMICA_VERSION')) {
    define('FLACSO_OFERTA_ACADEMICA_VERSION', FLACSO_URUGUAY_VERSION);
}
if (!defined('FLACSO_OFERTA_ACADEMICA_DATA_ONLY')) {
    define('FLACSO_OFERTA_ACADEMICA_DATA_ONLY', true);
}

// Cargar clases principales
flacso_safe_require('modules/oferta-academica/includes/class-cpt-oferta-academica.php');
flacso_safe_require('modules/oferta-academica/includes/class-cpt-tabla-precio.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-taxonomies.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-page-adapter.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-renderer.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-blocks.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-data-importer.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-data-admin.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-data-migration.php');
flacso_safe_require('modules/oferta-academica/includes/class-tabla-precio-schema.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-data-schema.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-docentes-integration.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-seminarios-integration.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-data-metabox.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-consulta-form.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-seminarios-routes.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-seminarios-admin-links.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-rest-api.php');
flacso_safe_require('modules/oferta-academica/includes/class-academic-offer-api.php');

// Inicializar
add_action('init', function() {
    CPT_Oferta_Academica::init();
    CPT_Tabla_Precio::init();
    Oferta_Taxonomies::init();
    if (class_exists('Seminario_Taxonomies')) {
        Seminario_Taxonomies::maybe_backfill_program_relationships();
    }
    Oferta_Page_Adapter::init();
    Tabla_Precio_Schema::init();
    Oferta_Data_Schema::init();
    Oferta_Blocks::init();
    Oferta_Data_Admin::init();
    Oferta_Data_Migration::init();
    Oferta_Docentes_Integration::init();
    Oferta_Seminarios_Integration::init();
    Oferta_Data_MetaBox::init();
    Oferta_Consulta_Form::init();
    Oferta_Seminarios_Routes::init();
    Oferta_Seminarios_Admin_Links::init();
    Oferta_Rest_API::init();
}, 5); // Prioridad 5 para que se ejecute antes

add_action('after_setup_theme', function() {
    add_theme_support('post-thumbnails', ['oferta-academica']);
});
