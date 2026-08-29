<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra el Custom Post Type: oferta-academica
 */
class CPT_Oferta_Academica {
    
    public static function init(): void {
        self::register_post_type();
        add_action('template_redirect', [self::class, 'maybe_render_formacion_virtual_page'], 1);
        add_filter('request', [self::class, 'resolve_migrated_seminar_request'], 5);
    }

    public static function maybe_render_formacion_virtual_page(): void {
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        $path = trim(wp_parse_url($request_uri, PHP_URL_PATH), '/');
        
        if ($path === 'formacion') {
            global $wp_query;
            if ($wp_query) {
                $wp_query->is_404 = false;
                $wp_query->is_singular = true;
                $wp_query->is_page = true;
                $wp_query->is_archive = false;
                $wp_query->is_home = false;
                $wp_query->set_404(false);
            }

            status_header(200);
            nocache_headers();

            $base_title = 'Oferta Académica';
            $site_name = trim((string) get_bloginfo('name'));
            $full_title = $site_name ? $base_title . ' - ' . $site_name : $base_title;

            add_filter('pre_get_document_title', static function () use ($full_title) { return $full_title; }, 999);
            add_filter('document_title_parts', static function ($parts) use ($base_title) {
                $parts['title'] = $base_title;
                unset($parts['tagline']);
                return $parts;
            }, 999);
            add_filter('wp_title', static function () use ($full_title) { return $full_title; }, 999);
            add_filter('rank_math/frontend/title', static function () use ($full_title) { return $full_title; }, 999);
            add_filter('wpseo_title', static function () use ($full_title) { return $full_title; }, 999);
            
            add_filter('flacso_oferta_academica_hero_image', static function($image) {
                return $image ? $image : 'https://flacso.edu.uy/wp-content/uploads/2025/11/primer-plano-de-ejecutivos-de-negocios-en-la-oficina-scaled.jpg';
            });

            $template = locate_template('page-formacion.php');
            if ($template) {
                include $template;
            } else {
                wp_die(
                    esc_html__('Falta el template page-formacion.php en el theme activo.', 'flacso-uruguay'),
                    esc_html__('Template no disponible', 'flacso-uruguay'),
                    ['response' => 500]
                );
            }
            exit;
        }
    }



    public static function add_og_meta_tags(): void {
        if (is_singular('oferta-academica') && !has_action('wp_head', 'jetpack_og_tags')) {
            $post_id = get_the_ID();
            $titulo = get_the_title();
            $url = get_permalink();
            $imagen_url = get_the_post_thumbnail_url($post_id, 'full');
            $descripcion = get_the_excerpt();

            if (empty($descripcion)) {
                $descripcion = wp_trim_words(strip_shortcodes(get_post_field('post_content', $post_id)), 30, '...');
            }

            echo '<meta property="og:type" content="article" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($titulo) . '" />' . "\n";
            if ($descripcion) {
                echo '<meta property="og:description" content="' . esc_attr($descripcion) . '" />' . "\n";
            }
            echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
            if ($imagen_url) {
                echo '<meta property="og:image" content="' . esc_url($imagen_url) . '" />' . "\n";
                echo '<meta property="og:image:width" content="1200" />' . "\n";
                echo '<meta property="og:image:height" content="630" />' . "\n";
            }
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($titulo) . '" />' . "\n";
            if ($descripcion) {
                echo '<meta name="twitter:description" content="' . esc_attr($descripcion) . '" />' . "\n";
            }
            if ($imagen_url) {
                echo '<meta name="twitter:image" content="' . esc_url($imagen_url) . '" />' . "\n";
            }
        }
    }

    public static function register_post_type(): void {
        $labels = [
            'name'                  => 'Oferta Académica',
            'singular_name'         => 'Oferta Académica',
            'menu_name'             => 'Oferta Académica',
            'name_admin_bar'        => 'Oferta Académica',
            'add_new'               => 'Añadir Nueva',
            'add_new_item'          => 'Añadir Nueva Oferta Académica',
            'new_item'              => 'Nueva Oferta Académica',
            'edit_item'             => 'Editar Oferta Académica',
            'view_item'             => 'Ver Oferta Académica',
            'all_items'             => 'Todas las Ofertas',
            'search_items'          => 'Buscar Ofertas Académicas',
            'not_found'             => 'No se encontraron ofertas académicas',
            'not_found_in_trash'    => 'No hay ofertas académicas en la papelera',
        ];

        $args = [
            'labels'                => $labels,
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'show_in_rest'          => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'formacion/%tipo-oferta-academica%', 'with_front' => false],
            'capability_type'       => 'post',
            'has_archive'           => false,
            'hierarchical'          => false,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-welcome-learn-more',
            'supports'              => ['title', 'thumbnail', 'revisions'],
            'taxonomies'            => ['tipo-oferta-academica', 'area_tematica'],
        ];

        register_post_type('oferta-academica', $args);
        add_filter('post_type_link', [self::class, 'oferta_academica_permalink'], 10, 2);
        
        // REGLA PARA PÁGINAS LEGACY (_old)
        // Evita que el CPT secuestre las páginas de WordPress que terminan en _old
        add_action('init', function() {
            add_rewrite_rule(
                '^formacion/([^/]+)/([^/]+_old)/?$',
                'index.php?pagename=formacion/$matches[1]/$matches[2]',
                'top'
            );
        }, 11);
    }

    /**
     * Reemplaza el tag %tipo-oferta-academica% en el permalink con el plural del término asociado.
     */
    public static function oferta_academica_permalink($post_link, $post) {
        if (is_object($post) && $post->post_type === 'oferta-academica') {
            if (strpos($post_link, '%tipo-oferta-academica%') !== false) {
                $terms = wp_get_object_terms($post->ID, 'tipo-oferta-academica');
                if (!is_wp_error($terms) && !empty($terms) && is_object($terms[0])) {
                    $slug = $terms[0]->slug;
                    
                    $segments = FLACSO_Oferta_Academica::segmentos_url();
                    $plural_slug = $segments[$slug] ?? 'otros';
                    
                    $post_link = str_replace('%tipo-oferta-academica%', $plural_slug, $post_link);
                } else {
                    $post_link = str_replace('%tipo-oferta-academica%', 'otros', $post_link);
                }
            }
        }
        return $post_link;
    }

    /**
     * Las reglas del CPT legacy siguen activas en Release A. Si el slug ya fue
     * migrado, redirigimos internamente la consulta al nuevo post type sin 301 ni
     * cambio de URL publica.
     */
    public static function resolve_migrated_seminar_request(array $query_vars): array {
        if (empty($query_vars['seminario']) || !empty($query_vars['oferta-academica'])) {
            return $query_vars;
        }
        $slug = sanitize_title((string) $query_vars['seminario']);
        $offer = get_page_by_path($slug, OBJECT, FLACSO_Oferta_Academica::POST_TYPE);
        if (!$offer || FLACSO_Oferta_Academica::get_tipo((int) $offer->ID) !== FLACSO_Oferta_Academica::TIPO_SEMINARIO) {
            return $query_vars;
        }
        unset($query_vars['seminario']);
        $query_vars['post_type'] = FLACSO_Oferta_Academica::POST_TYPE;
        $query_vars['name'] = $slug;
        return $query_vars;
    }
}
