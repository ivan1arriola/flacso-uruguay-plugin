<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_Templates
{
    public static function register_preinscripcion_route()
    {
        add_rewrite_tag('%flacso_preinscripcion%', '([0-1])');
        add_rewrite_rule('^formacion/preinscripciones/?$', 'index.php?flacso_preinscripcion=1', 'top');
    }

    public static function add_query_vars($vars)
    {
        $vars[] = 'flacso_preinscripcion';
        return $vars;
    }

    public static function single_template($template)
    {
        if (is_singular('seminario')) {
            $plugin_template = FLACSO_SEMINARIO_PATH . 'templates/single-seminario.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public static function preinscripcion_template($template)
    {
        $is_endpoint = get_query_var('flacso_preinscripcion');
        if ($is_endpoint) {
            $plugin_template = FLACSO_SEMINARIO_PATH . 'templates/preinscripcion-seminario.php';
            if (file_exists($plugin_template)) {
                status_header(200);
                return $plugin_template;
            }
        }
        return $template;
    }

    public static function enqueue_public_assets()
    {
        $is_seminarios_listing = is_post_type_archive('seminario') || is_page('seminarios');

        if (is_singular('seminario') || $is_seminarios_listing || is_page('contactar-seminario') || get_query_var('flacso_preinscripcion')) {
            // Ensure document title reflects page context
            add_filter('document_title_parts', array(__CLASS__, 'filter_document_title'));
            // Add Open Graph meta tags for social sharing
            add_action('wp_head', array(__CLASS__, 'add_og_meta_tags'), 5);
            // Enqueue Kadence compatibility CSS
            wp_enqueue_style(
                'flacso-kadence-compat',
                plugins_url('modules/seminarios/assets/kadence-compat.css', FLACSO_URUGUAY_FILE),
                array(),
                FLACSO_SEMINARIO_VERSION
            );

            // Legacy public CSS (if needed for additional styles)
            $public_css_path = dirname(__DIR__) . '/assets/css/public.css';
            if (file_exists($public_css_path)) {
                wp_enqueue_style(
                    'flacso-seminario-public',
                    plugins_url('modules/seminarios/assets/css/public.css', FLACSO_URUGUAY_FILE),
                    array('flacso-kadence-compat'),
                    FLACSO_SEMINARIO_VERSION
                );
            }

            if ($is_seminarios_listing) {
                wp_enqueue_style(
                    'flacso-seminarios-listado',
                    plugins_url('modules/seminarios/assets/css/seminarios-listado.css', FLACSO_URUGUAY_FILE),
                    array('flacso-kadence-compat'),
                    FLACSO_SEMINARIO_VERSION
                );

                if (wp_style_is('bootstrap-icons', 'registered') && !wp_style_is('bootstrap-icons', 'enqueued')) {
                    wp_enqueue_style('bootstrap-icons');
                } elseif (!wp_style_is('bootstrap-icons', 'registered') && !wp_style_is('bootstrap-icons', 'enqueued')) {
                    wp_enqueue_style(
                        'bootstrap-icons',
                        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
                        array(),
                        '1.11.3'
                    );
                }
            }
        }
    }

    /**
     * Adjust document title for plugin routes using Kadence or other themes
     */
    public static function filter_document_title($parts)
    {
        // Preinscripción endpoint title
        if (get_query_var('flacso_preinscripcion')) {
            $seminario_id = isset($_GET['ID']) ? intval($_GET['ID']) : 0;
            $title = __('Preinscripción', 'cpt-seminario');
            if ($seminario_id) {
                $seminario_titulo = get_the_title($seminario_id);
                if (!empty($seminario_titulo)) {
                    $title .= ' – ' . $seminario_titulo;
                }
            }
            $parts['title'] = $title;
        }

        // Single seminario title already handled by theme, but ensure clarity
        if (is_singular('seminario')) {
            $parts['title'] = single_post_title('', false);
        }

        return $parts;
    }

    /**
     * Add Open Graph meta tags for social media sharing
     */
    public static function add_og_meta_tags()
    {
        // Preinscripción endpoint
        if (get_query_var('flacso_preinscripcion')) {
            $seminario_id = isset($_GET['ID']) ? intval($_GET['ID']) : 0;
            if ($seminario_id && get_post_type($seminario_id) === 'seminario') {
                $seminario_titulo = get_the_title($seminario_id);
                $seminario_url = add_query_arg('ID', $seminario_id, home_url('/formacion/preinscripciones/'));
                $imagen_url = get_the_post_thumbnail_url($seminario_id, 'full');
                $descripcion = get_the_excerpt($seminario_id);
                if (empty($descripcion)) {
                    $descripcion = 'Completa el formulario de preinscripción para ' . $seminario_titulo;
                }

                echo '<meta property="og:type" content="website" />' . "\n";
                echo '<meta property="og:title" content="' . esc_attr('Preinscripción – ' . $seminario_titulo) . '" />' . "\n";
                echo '<meta property="og:description" content="' . esc_attr($descripcion) . '" />' . "\n";
                echo '<meta property="og:url" content="' . esc_url($seminario_url) . '" />' . "\n";
                if ($imagen_url) {
                    echo '<meta property="og:image" content="' . esc_url($imagen_url) . '" />' . "\n";
                    echo '<meta property="og:image:width" content="1200" />' . "\n";
                    echo '<meta property="og:image:height" content="630" />' . "\n";
                }
                echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
                echo '<meta name="twitter:title" content="' . esc_attr('Preinscripción – ' . $seminario_titulo) . '" />' . "\n";
                echo '<meta name="twitter:description" content="' . esc_attr($descripcion) . '" />' . "\n";
                if ($imagen_url) {
                    echo '<meta name="twitter:image" content="' . esc_url($imagen_url) . '" />' . "\n";
                }
            }
        }
        
        // Single seminario (enhance if theme doesn't add OG tags)
        if (is_singular('seminario') && !has_action('wp_head', 'jetpack_og_tags')) {
            $seminario_id = get_the_ID();
            $seminario_titulo = get_the_title();
            $seminario_url = get_permalink();
            $imagen_url = get_the_post_thumbnail_url($seminario_id, 'full');
            $descripcion = get_the_excerpt();

            echo '<meta property="og:type" content="article" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($seminario_titulo) . '" />' . "\n";
            if ($descripcion) {
                echo '<meta property="og:description" content="' . esc_attr($descripcion) . '" />' . "\n";
            }
            echo '<meta property="og:url" content="' . esc_url($seminario_url) . '" />' . "\n";
            if ($imagen_url) {
                echo '<meta property="og:image" content="' . esc_url($imagen_url) . '" />' . "\n";
                echo '<meta property="og:image:width" content="1200" />' . "\n";
                echo '<meta property="og:image:height" content="630" />' . "\n";
            }
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($seminario_titulo) . '" />' . "\n";
            if ($descripcion) {
                echo '<meta name="twitter:description" content="' . esc_attr($descripcion) . '" />' . "\n";
            }
            if ($imagen_url) {
                echo '<meta name="twitter:image" content="' . esc_url($imagen_url) . '" />' . "\n";
            }
        }
    }

    public static function seminarios_template($template)
    {
        if (is_post_type_archive('seminario') || is_page('seminarios')) {
            $plugin_template = FLACSO_SEMINARIO_PATH . 'templates/seminarios-listado.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public static function consulta_template($template)
    {
        if (is_page('contactar-seminario')) {
            $plugin_template = FLACSO_SEMINARIO_PATH . 'templates/consulta-seminario.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
}
