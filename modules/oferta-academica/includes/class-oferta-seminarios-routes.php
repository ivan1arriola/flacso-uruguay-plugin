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
     * Registrar la regla de reescritura para /seminarios/
     */
    public static function register_endpoints(): void {
        // Regla específica para el CPT oferta-academica
        add_rewrite_rule(
            '^programa/([^/]+)/seminarios/?$',
            'index.php?oferta-academica=$matches[1]&seminarios=1',
            'top'
        );
        
        // Mantener el endpoint por si acaso para otros tipos
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
        if (is_tax('tipo-oferta-academica')) {
            $plugin_template = self::template_path('taxonomy-tipo-oferta-academica.php');
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        $is_seminarios_endpoint = self::is_seminarios_endpoint_request();
        $is_oferta_singular = is_singular('oferta-academica');

        // Solo intervenir en:
        // - endpoint /seminarios/ de programa
        // - single del CPT oferta-academica
        if (!$is_seminarios_endpoint && !$is_oferta_singular) {
            return $template;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        // Resiliencia extrema: Si no hay ID, intentar resolverlo por el slug en la URL
        if (!$post_id) {
            $path = self::request_path();
            if (preg_match('|/programa/([^/]+)/|', $path, $matches)) {
                $slug = $matches[1];
                $post_obj = get_page_by_path($slug, OBJECT, 'oferta-academica');
                if ($post_obj) {
                    $post_id = $post_obj->ID;
                } else {
                    // Intentar como página si no es CPT
                    $post_obj = get_page_by_path($slug, OBJECT, 'page');
                    if ($post_obj) {
                        $post_id = $post_obj->ID;
                    }
                }
            }
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
                // Importante: para el resto de la lógica usamos el ID de la oferta
                $post_id = $associated_ofertas[0]; 
            }
        }

        if ($is_oferta) {
            $template_name = $is_seminarios_endpoint
                ? 'oferta-seminarios.php'
                : 'single-oferta-academica.php';

            $plugin_template = self::template_path($template_name);
            
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Redirige la vista individual de oferta-academica a su página asociada si existe.
     * Esto oculta la vista propia del CPT en favor de la página maquetada.
     */
    public static function maybe_redirect_oferta_singular(): void {
        // No redirigir si es el endpoint de seminarios
        if (self::is_seminarios_endpoint_request()) {
            return;
        }

        if (is_singular('oferta-academica')) {
            $post_id = get_queried_object_id();
            if (class_exists('Oferta_Page_Adapter')) {
                $associated_page_id = Oferta_Page_Adapter::get_page_id($post_id);
                if ($associated_page_id) {
                    wp_redirect(get_permalink($associated_page_id), 301);
                    exit;
                }
            }
        }
    }

    /**
     * Cargar assets necesarios para la vista de seminarios
     */
    public static function enqueue_assets(): void {
        if (!self::is_seminarios_endpoint_request()) {
            return;
        }

        // Cargar estilos base de la oferta académica
        if (class_exists('Oferta_Renderer')) {
            Oferta_Renderer::enqueue_styles();
        }

        // Cargar estilos premium del listado de seminarios
        $seminarios_css_url = plugins_url('modules/seminarios/assets/css/seminarios-listado.css', FLACSO_URUGUAY_FILE);
        $kadence_css_url = plugins_url('modules/seminarios/assets/kadence-compat.css', FLACSO_URUGUAY_FILE);

        wp_enqueue_style('flacso-kadence-compat', $kadence_css_url, [], FLACSO_URUGUAY_VERSION);
        wp_enqueue_style('flacso-seminarios-listado', $seminarios_css_url, ['flacso-kadence-compat'], FLACSO_URUGUAY_VERSION);

        // Asegurar iconos de Bootstrap
        if (!wp_style_is('bootstrap-icons', 'enqueued')) {
            wp_enqueue_style(
                'bootstrap-icons',
                'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
                [],
                '1.11.3'
            );
        }
    }

    /**
     * Detecta si el request actual corresponde al endpoint /seminarios/.
     *
     * Nota: get_query_var('seminarios') puede devolver string vacío cuando
     * el endpoint existe sin valor, por eso usamos default null para
     * distinguir ausencia real del query var.
     */
    private static function is_seminarios_endpoint_request(): bool {
        $query_var = get_query_var('seminarios', null);
        if ($query_var !== null) {
            return true;
        }

        $path = trailingslashit(self::request_path());
        return (bool) preg_match('#/programa/[^/]+/seminarios/#', $path);
    }

    /**
     * Obtiene el path actual de forma segura.
     */
    private static function request_path(): string {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($request_uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }

    /**
     * Ruta absoluta a templates del módulo oferta-academica.
     */
    private static function template_path(string $filename): string {
        return dirname(__DIR__) . '/templates/' . ltrim($filename, '/');
    }
}
