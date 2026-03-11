<?php
/**
 * Modulo integrado: registra CPT, taxonomias y bloques de docentes.
 */

if (!defined('ABSPATH')) exit;

if (defined('DP_DOCENTES_MODULE_LOADED')) {
    return;
}

define('DP_DOCENTES_MODULE_LOADED', true);

if (!function_exists('dp_docentes_safe_require')) {
    /**
     * Carga defensiva un archivo relativo al módulo evitando fatals.
     */
    function dp_docentes_safe_require(string $relative_path): bool {
        $relative_path = ltrim($relative_path, '/');

        if (function_exists('flacso_posgrados_safe_require')) {
            return flacso_posgrados_safe_require(sprintf('docentes/%s', $relative_path));
        }

        $path = plugin_dir_path(__FILE__) . $relative_path;

        if (!file_exists($path)) {
            error_log(sprintf('[DP Docentes] El fichero %s no existe.', $path));
            return false;
        }

        try {
            require_once $path;
        } catch (Throwable $e) {
            error_log(sprintf('[DP Docentes] Error al cargar %s: %s', $relative_path, $e->getMessage()));
            return false;
        }

        return true;
    }
}
// ==========================
// Funciones (heredadas de core helpers o posgrados)
// ==========================

// Usar funciones del core si existen, de lo contrario definir localmente
if (!function_exists('dp_is_docentes_view')) {
    function dp_is_docentes_view() {
        return is_post_type_archive('docente')
            || is_singular('docente')
            || is_tax('equipo-docente')
            || is_page('equipo-docente');
    }
}

if (!function_exists('dp_docentes_asset_version')) {
    function dp_docentes_asset_version(string $relative_path): string {
        $absolute_path = dirname(__DIR__) . '/' . ltrim($relative_path, '/');

        if (file_exists($absolute_path)) {
            return (string) filemtime($absolute_path);
        }

        return defined('FLACSO_POSGRADOS_SLUG') ? FLACSO_POSGRADOS_SLUG : '1.0.1';
    }
}

function dp_docentes_register_assets(): void {
    static $registered = false;

    if ($registered) {
        return;
    }

    $bootstrap_css = 'assets/css/docentes-bootstrap-scoped.min.css';
    $templates_css = 'assets/css/docentes-templates.css';
    $directory_js  = 'assets/js/docentes-directory.js';

    wp_register_style(
        'flacso-docentes-bootstrap',
        plugins_url('../' . $bootstrap_css, __FILE__),
        [],
        dp_docentes_asset_version($bootstrap_css)
    );

    wp_register_style(
        'flacso-docentes-bootstrap-icons',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        ['flacso-docentes-bootstrap'],
        '1.11.3'
    );

    wp_register_style(
        'flacso-docentes-templates',
        plugins_url('../' . $templates_css, __FILE__),
        ['flacso-docentes-bootstrap', 'flacso-docentes-bootstrap-icons'],
        dp_docentes_asset_version($templates_css)
    );

    wp_register_style(
        'bootstrap-avatar',
        'https://cdn.jsdelivr.net/npm/bootstrap-avatar@latest/dist/avatar.min.css',
        ['flacso-docentes-bootstrap'],
        null
    );

    wp_register_script(
        'flacso-docentes-bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        [],
        '5.3.3',
        true
    );

    wp_register_script(
        'flacso-docentes-directory',
        plugins_url('../' . $directory_js, __FILE__),
        ['flacso-docentes-bootstrap-js'],
        dp_docentes_asset_version($directory_js),
        true
    );

    $registered = true;
}

function dp_docentes_enqueue_assets(): void {
    static $enqueued = false;

    if ($enqueued) {
        return;
    }

    dp_docentes_register_assets();

    wp_enqueue_style('flacso-docentes-bootstrap');
    wp_enqueue_style('flacso-docentes-bootstrap-icons');
    wp_enqueue_style('flacso-docentes-templates');
    wp_enqueue_style('bootstrap-avatar');

    wp_enqueue_script('flacso-docentes-bootstrap-js');
    wp_enqueue_script('flacso-docentes-directory');

    $enqueued = true;
}

add_action('wp_enqueue_scripts', function() {
    if (dp_is_docentes_view()) {
        dp_docentes_enqueue_assets();
    }
});

add_filter('body_class', function($classes) {
    if (dp_is_docentes_view()) {
        $classes[] = 'dp-docentes-view';
    }
    return $classes;
});


// Incluir todos los archivos del plugin
$dp_docentes_dependencies = [
    'includes/utils.php',
    'includes/cpt-docente.php',
    'includes/taxonomy-equipo-docente.php',
    'includes/meta-docente.php',
    'includes/meta-equipo-docente.php',
    'includes/admin-columns.php',
    'includes/admin-filters.php',
    'includes/admin.php',
    'includes/renderers.php',
    'includes/blocks.php',
    'includes/rest-api.php',
];

foreach ($dp_docentes_dependencies as $dependency) {
    if (!dp_docentes_safe_require($dependency)) {
        return;
    }
}


// ==========================
// Redirigir a templates del plugin
// ==========================
add_filter('template_include', function($template) {
    if (is_post_type_archive('docente')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/archive-docente.php';
        if (file_exists($plugin_template)) return $plugin_template;
    }

    if (is_singular('docente')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/single-docente.php';
        if (file_exists($plugin_template)) return $plugin_template;
    }

    if (is_page('equipo-docente')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/archive-equipo-docente.php';
        if (file_exists($plugin_template)) return $plugin_template;
    }

    if (is_tax('equipo-docente')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/taxonomy-equipo-docente.php';
        if (file_exists($plugin_template)) return $plugin_template;
    }

    return $template;
});


// Incluir bloques
// (Los bloques ya se cargan dentro de $dp_docentes_dependencies)

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
