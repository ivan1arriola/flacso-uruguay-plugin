<?php
/**
 * Módulo de Docentes - FLACSO Uruguay
 * Gestión de docentes y equipos docentes
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar si los plugins antiguos están activos para evitar conflictos
if (class_exists('CPT_Docente') || class_exists('Docente_Taxonomies') || class_exists('Docente_Meta')) {
    if (!defined('WP_CLI') && is_admin() && !wp_doing_ajax()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>FLACSO Uruguay:</strong> Detectamos que tienes plugins antiguos activos (cpt-docentes, etc). Por favor desactívalos para usar este plugin consolidado.</p></div>';
        });
    }
    return;
}

if (!defined('FLACSO_MAIN_DOCENTES_MODULE_LOADED')) {
    define('FLACSO_MAIN_DOCENTES_MODULE_LOADED', true);
}

// Cargar clases principales del módulo
flacso_safe_require('modules/docentes/includes/class-cpt-docente.php');
flacso_safe_require('modules/docentes/includes/class-docente-taxonomies.php');
flacso_safe_require('modules/docentes/includes/class-docente-meta.php');

// Cargar archivos de utilidades
flacso_safe_require('modules/docentes/includes/meta-equipo-docente.php');
flacso_safe_require('modules/docentes/includes/utils.php');
flacso_safe_require('modules/docentes/includes/renderers.php');
flacso_safe_require('modules/docentes/includes/rest-api.php');
flacso_safe_require('modules/docentes/includes/blocks.php');

$is_admin_context = is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax());
if ($is_admin_context) {
    flacso_safe_require('modules/docentes/includes/admin.php');
    flacso_safe_require('modules/docentes/includes/admin-columns.php');
    flacso_safe_require('modules/docentes/includes/admin-filters.php');
}

// Inicializar módulo
add_action('init', function() {
    // Registrar CPT y taxonomías
    CPT_Docente::init();
    Docente_Taxonomies::init();
    Docente_Meta::init();
    
    // Registrar assets
    add_action('wp_enqueue_scripts', function() {
        if (dp_is_docentes_view()) {
            dp_docentes_register_assets();
            dp_docentes_enqueue_assets();
        }
    });
    
    // Filtros para body class
    add_filter('body_class', function($classes) {
        if (dp_is_docentes_view()) {
            $classes[] = 'dp-docentes-view';
        }
        return $classes;
    });
    
    // Redirigir a templates del módulo
    add_filter('template_include', function($template) {
        if (is_singular('docente')) {
            $plugin_template = FLACSO_URUGUAY_PATH . 'modules/docentes/templates/single-docente.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }
        if (is_post_type_archive('docente')) {
            $plugin_template = FLACSO_URUGUAY_PATH . 'modules/docentes/templates/archive-docente.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }
        if (is_tax('equipo-docente')) {
            $plugin_template = FLACSO_URUGUAY_PATH . 'modules/docentes/templates/taxonomy-equipo-docente.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }
        return $template;
    });
}, 10);

// Hook de activación del módulo
add_action('flacso_activate_module_docentes', function() {
    CPT_Docente::init();
    Docente_Taxonomies::init();
    flush_rewrite_rules();
});

if (!function_exists('dp_purge_equipo_docente_data_once')) {
    function dp_purge_equipo_docente_data_once(): void {
        $flag_key = 'dp_equipo_docente_purged_v1';
        if (get_option($flag_key)) {
            return;
        }

        global $wpdb;
        $taxonomy = 'equipo-docente';

        $term_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
                $taxonomy
            )
        );

        if (!empty($term_ids)) {
            $term_ids = array_values(array_unique(array_map('intval', $term_ids)));
            foreach ($term_ids as $term_id) {
                wp_delete_term($term_id, $taxonomy);
            }
        }

        $meta_keys = [
            'equipo_docente_color',
            'equipo_docente_page_id',
            'equipo_docente_relacion_nombre',
            'equipo_docente_autosync',
            'color_equipo',
        ];

        if (!empty($meta_keys)) {
            $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
            $sql = "DELETE FROM {$wpdb->termmeta} WHERE meta_key IN ({$placeholders})";
            $wpdb->query($wpdb->prepare($sql, $meta_keys));
        }

        update_option($flag_key, 1, false);
    }
}

add_action('init', 'dp_purge_equipo_docente_data_once', 200);
