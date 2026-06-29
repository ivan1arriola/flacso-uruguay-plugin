<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_Templates
{
    public static function register_preinscripcion_route()
    {
        add_rewrite_tag('%flacso_preinscripcion%', '([0-1])');
        // New route (child of seminario)
        add_rewrite_rule('^formacion/seminarios/([^/]+)/preinscripcion/?$', 'index.php?seminario=$matches[1]&flacso_preinscripcion=1', 'top');
        // Legacy route
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
            $overridden = locate_template(array('single-seminario.php'));
            if ($overridden !== '') {
                return $overridden;
            }

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
            $overridden = locate_template(array('preinscripcion-seminario.php'));
            if ($overridden !== '') {
                status_header(200);
                return $overridden;
            }

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

            // Enqueue modern preinscription styles and external libs if it's the preinscription endpoint
            if (get_query_var('flacso_preinscripcion')) {
                wp_enqueue_style('intl-tel-input-css', 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.12.4/build/css/intlTelInput.css', array(), '25.12.4');
                wp_enqueue_style('country-select-js-css', 'https://cdn.jsdelivr.net/npm/country-select-js@2.0.1/build/css/countrySelect.min.css', array(), '2.0.1');
                
                wp_enqueue_style(
                    'flacso-formulario-styles',
                    plugins_url('modules/preinscripcion/includes/assets/styles.css', FLACSO_URUGUAY_FILE),
                    array('intl-tel-input-css', 'country-select-js-css'),
                    FLACSO_SEMINARIO_VERSION
                );
            }

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
            $seminario_id = isset($_GET['ID']) ? intval($_GET['ID']) : get_queried_object_id();
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
            $seminario_id = isset($_GET['ID']) ? intval($_GET['ID']) : get_queried_object_id();
            if ($seminario_id && get_post_type($seminario_id) === 'seminario') {
                $seminario_titulo = get_the_title($seminario_id);
                $seminario_url = home_url('/formacion/seminarios/' . get_post_field('post_name', $seminario_id) . '/preinscripcion/');
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
            if (empty($descripcion)) {
                $descripcion = wp_trim_words(strip_shortcodes(get_post_field('post_content', $seminario_id)), 30, '...');
            }

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
            $overridden = locate_template(array('seminarios-listado.php'));
            if ($overridden !== '') {
                return $overridden;
            }

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
            $overridden = locate_template(array('consulta-seminario.php'));
            if ($overridden !== '') {
                return $overridden;
            }

            $plugin_template = FLACSO_SEMINARIO_PATH . 'templates/consulta-seminario.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
}
