<?php
/**
 * Gestiona las rutas personalizadas para subpáginas de seminarios en ofertas académicas.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oferta_Seminarios_Routes {

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_endpoints']);
        add_filter('query_vars', [__CLASS__, 'add_query_vars']);
        add_filter('template_include', [__CLASS__, 'template_include'], 15);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Registrar el endpoint /seminarios/
     */
    public static function register_endpoints(): void {
        add_rewrite_endpoint('seminarios', EP_PERMALINK | EP_PAGES);
    }

    /**
     * Asegurar que WordPress reconozca la variable de consulta
     */
    public static function add_query_vars(array $vars): array {
        $vars[] = 'seminarios';
        return $vars;
    }

    /**
     * Redirigir a la plantilla personalizada si el endpoint está activo
     */
    public static function template_include(string $template): string {
        $is_seminarios_endpoint = get_query_var('seminarios') !== false;
        
        if (!$is_seminarios_endpoint) {
            return $template;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (!$post_id) {
            return $template;
        }

        // Caso 1: Es el CPT oferta-academica directamente
        $is_oferta = get_post_type($post_id) === 'oferta-academica';
        
        // Caso 2: Es una página asociada a una oferta académica (vía Adapter)
        if (!$is_oferta) {
            $associated_ofertas = get_posts([
                'post_type' => 'oferta-academica',
                'meta_key' => '_oferta_page_id',
                'meta_value' => $post_id,
                'posts_per_page' => 1,
                'fields' => 'ids',
            ]);
            if (!empty($associated_ofertas)) {
                $is_oferta = true;
            }
        }

        if ($is_oferta) {
            $plugin_template = FLACSO_OFERTA_ACADEMICA_PATH . 'templates/oferta-seminarios.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Cargar assets necesarios para la vista de seminarios
     */
    public static function enqueue_assets(): void {
        if (get_query_var('seminarios') === false) {
            return;
        }

        // Reutilizar estilos de la oferta académica si es necesario
        if (class_exists('Oferta_Renderer')) {
            Oferta_Renderer::enqueue_styles();
        }

        // Estilos específicos para el listado de seminarios si existen
        if (class_exists('Seminario_Templates')) {
             wp_enqueue_style(
                'flacso-seminarios-listado',
                plugins_url('modules/seminarios/assets/css/seminarios-listado.css', FLACSO_URUGUAY_FILE),
                [],
                FLACSO_URUGUAY_VERSION
            );
        }
    }
}
