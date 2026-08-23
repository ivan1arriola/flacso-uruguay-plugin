<?php
/**
 * Módulo de Oferta Académica - FLACSO Uruguay.
 */

if (!defined('ABSPATH')) {
    exit;
}

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

$required_files = [
    'class-cpt-oferta-academica.php',
    'class-cpt-tabla-precio.php',
    'class-oferta-taxonomies.php',
    'class-oferta-page-adapter.php',
    'class-oferta-renderer.php',
    'class-oferta-blocks.php',
    'class-oferta-data-importer.php',
    'class-oferta-data-admin.php',
    'class-oferta-data-migration.php',
    'class-tabla-precio-schema.php',
    'class-oferta-data-schema.php',
    'class-oferta-docentes-integration.php',
    'class-oferta-seminarios-integration.php',
    'class-oferta-data-metabox.php',
    'class-oferta-consulta-form.php',
    'class-oferta-seminarios-routes.php',
    'class-oferta-seminarios-admin-links.php',
    'class-oferta-rest-api.php',
    'homepage.php',
];

foreach ($required_files as $file) {
    flacso_safe_require('modules/oferta-academica/includes/' . $file);
}

add_action('init', static function (): void {
    CPT_Oferta_Academica::init();
    CPT_Tabla_Precio::init();
    Oferta_Taxonomies::init();
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
}, 5);

add_action('after_setup_theme', static function (): void {
    add_theme_support('post-thumbnails', ['oferta-academica']);
});
