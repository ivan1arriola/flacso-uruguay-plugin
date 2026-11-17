<?php
/**
 * Modulo de Docentes - FLACSO Uruguay
 * Gestion de perfiles individuales de docentes
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar si los plugins antiguos estan activos para evitar conflictos.
if (class_exists('CPT_Docente') || class_exists('Docente_Taxonomies') || class_exists('Docente_Meta')) {
    if (!defined('WP_CLI') && is_admin() && !wp_doing_ajax()) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>FLACSO Uruguay:</strong> Detectamos que tienes plugins antiguos activos (cpt-docentes, etc). Por favor desactivalos para usar este plugin consolidado.</p></div>';
        });
    }
    return;
}

define('FLACSO_MAIN_DOCENTES_MODULE_LOADED', true);

// Cargar clases principales del modulo.
flacso_safe_require('modules/docentes/includes/class-cpt-docente.php');
flacso_safe_require('modules/docentes/includes/class-docente-meta.php');
flacso_safe_require('modules/docentes/includes/class-docente-appearances.php');

// Cargar archivos de utilidades.
flacso_safe_require('modules/docentes/includes/assets.php');
flacso_safe_require('modules/docentes/includes/utils.php');
flacso_safe_require('modules/docentes/includes/renderers.php');
flacso_safe_require('modules/docentes/includes/rest-api.php');
flacso_safe_require('modules/docentes/includes/blocks.php');

$is_admin_context = is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax());
if ($is_admin_context) {
    flacso_safe_require('modules/docentes/includes/admin-columns.php');
    flacso_safe_require('modules/docentes/includes/admin-filters.php');
}

// Inicializar modulo.
add_action('init', function () {
    // Registrar CPT y metadatos.
    CPT_Docente::init();
    Docente_Meta::init();

    // Garantizar que el CPT docente no use taxonomias de equipos (Eliminado del todo)
    if (taxonomy_exists('equipo-docente') && function_exists('unregister_taxonomy_for_object_type')) {
        unregister_taxonomy_for_object_type('equipo-docente', 'docente');
    }

    // Registrar assets.
    add_action('wp_enqueue_scripts', function () {
        if (dp_is_docentes_view()) {
            if (function_exists('dp_docentes_register_assets')) {
                dp_docentes_register_assets();
            }
            if (function_exists('dp_docentes_enqueue_assets')) {
                dp_docentes_enqueue_assets();
            }
        }
    });

    // Filtros para body class.
    add_filter('body_class', function ($classes) {
        if (dp_is_docentes_view()) {
            $classes[] = 'dp-docentes-view';
        }
        return $classes;
    });

    // Redirigir a templates del modulo.
    add_filter('template_include', function ($template) {
        if (is_singular('docente')) {
            $overridden = locate_template(array('single-docente.php'));
            if ($overridden !== '') {
                return $overridden;
            }
        }

        if (is_post_type_archive('docente')) {
            $overridden = locate_template(array('archive-docente.php'));
            if ($overridden !== '') {
                return $overridden;
            }
        }

        return $template;
    });
}, 10);

// Hook de activacion del modulo.
add_action('flacso_activate_module_docentes', function () {
    CPT_Docente::init();
    flush_rewrite_rules();
});
