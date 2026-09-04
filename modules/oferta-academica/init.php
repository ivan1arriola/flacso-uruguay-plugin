<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_OFERTA_ACADEMICA_PATH')) {
    define('FLACSO_OFERTA_ACADEMICA_PATH', __DIR__ . '/');
}
if (!defined('FLACSO_OFERTA_ACADEMICA_URL')) {
    define('FLACSO_OFERTA_ACADEMICA_URL', plugin_dir_url(__FILE__));
}

// Entidades y persistencia finales. No hay migraciones ni adaptadores de lectura.
flacso_safe_require('modules/oferta-academica/includes/class-cpt-oferta-academica.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-admin-table-layout.php');
flacso_safe_require('modules/oferta-academica/includes/class-cpt-programa-academico.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-academica.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-admin-fields.php');
flacso_safe_require('modules/oferta-academica/includes/class-cohorte.php');
flacso_safe_require('modules/oferta-academica/includes/class-academic-document-source-admin.php');
// No cargar el editor legado: en algunos runtimes quedó una definición antigua
// de FLACSO_Academic_Team_Admin. El editor nuevo usa una clase sin colisiones.
flacso_safe_require('modules/oferta-academica/includes/class-academic-team-editor.php');
flacso_safe_require('modules/oferta-academica/includes/class-cpt-tabla-precio.php');
flacso_safe_require('modules/oferta-academica/includes/class-tabla-precio-schema.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-taxonomies.php');
flacso_safe_require('modules/oferta-academica/includes/class-academic-repositories.php');
flacso_safe_require('modules/oferta-academica/includes/class-academic-catalog.php');
flacso_safe_require('modules/oferta-academica/includes/class-academic-api.php');
flacso_safe_require('modules/oferta-academica/includes/class-django-api-client.php');
flacso_safe_require('modules/oferta-academica/includes/class-django-ajax-handlers.php');
flacso_safe_require('modules/oferta-academica/includes/class-oferta-consulta-form.php');

if (defined('WP_CLI') && WP_CLI) {
    flacso_safe_require('modules/oferta-academica/includes/class-academic-cli.php');
    flacso_safe_require('modules/oferta-academica/includes/class-academic-meta-normalizer.php');
}

add_action('init', static function (): void {
    CPT_Oferta_Academica::init();
    FLACSO_Oferta_Admin_Table_Layout::init();
    FLACSO_Programa_Academico::init();
    FLACSO_Oferta_Academica::register_meta();
    FLACSO_Cohorte::register();
    CPT_Tabla_Precio::init();
    Oferta_Taxonomies::init();
    Oferta_Consulta_Form::init();
}, 5);

FLACSO_Oferta_Admin_Fields::init();
FLACSO_Academic_Document_Source_Admin::init();
FLACSO_Academic_Team_Editor::init();
Tabla_Precio_Schema::init();
FLACSO_Academic_API::init();

add_action('after_setup_theme', static function (): void {
    add_theme_support('post-thumbnails', ['programa-academico', 'oferta-academica', 'seminario']);
});
