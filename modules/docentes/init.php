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

if (!defined('FLACSO_MAIN_DOCENTES_MODULE_LOADED')) {
    define('FLACSO_MAIN_DOCENTES_MODULE_LOADED', true);
}

// Cargar clases principales del modulo.
flacso_safe_require('modules/docentes/includes/class-cpt-docente.php');
flacso_safe_require('modules/docentes/includes/class-docente-meta.php');

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

    // Garantizar que el CPT docente no use taxonomias de equipos.
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
            $plugin_template = FLACSO_URUGUAY_PATH . 'modules/docentes/templates/single-docente.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        if (is_post_type_archive('docente')) {
            $plugin_template = FLACSO_URUGUAY_PATH . 'modules/docentes/templates/archive-docente.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
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

if (!function_exists('dp_purge_equipo_docente_data_once')) {
    function dp_purge_equipo_docente_data_once(): void {
        $flag_key = 'dp_equipo_docente_purged_v2';
        if (get_option($flag_key)) {
            return;
        }

        global $wpdb;
        $taxonomy = 'equipo-docente';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
                $taxonomy
            )
        );

        if (!empty($rows)) {
            $term_taxonomy_ids = [];
            $term_ids = [];

            foreach ($rows as $row) {
                $term_taxonomy_ids[] = (int) $row->term_taxonomy_id;
                $term_ids[] = (int) $row->term_id;
            }

            $term_taxonomy_ids = array_values(array_unique(array_filter($term_taxonomy_ids)));
            $term_ids = array_values(array_unique(array_filter($term_ids)));

            if (!empty($term_taxonomy_ids)) {
                $placeholders = implode(',', array_fill(0, count($term_taxonomy_ids), '%d'));
                $sql = "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$placeholders})";
                $wpdb->query($wpdb->prepare($sql, $term_taxonomy_ids));
            }

            if (!empty($term_ids)) {
                $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
                $sql = "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ({$placeholders})";
                $wpdb->query($wpdb->prepare($sql, $term_ids));
            }

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
                    $taxonomy
                )
            );

            // Eliminar terminos huerfanos sin taxonomias asociadas.
            $wpdb->query(
                "DELETE t FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.term_id IS NULL"
            );
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
