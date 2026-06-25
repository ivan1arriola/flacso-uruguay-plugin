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
        add_action('wp_head', [self::class, 'add_og_meta_tags'], 5);
        add_action('template_redirect', [self::class, 'maybe_render_formacion_virtual_page'], 1);
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

            get_header();
            echo Oferta_Renderer::render_oferta_pagina([
                'heroTitle' => 'Oferta Académica',
                'heroImageId' => 22981,
            ]);
            get_footer();
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
                    
                    // Pluralizar el slug
                    $plural_slug = $slug;
                    if ($slug === 'maestria') {
                        $plural_slug = 'maestrias';
                    } elseif ($slug === 'especializacion') {
                        $plural_slug = 'especializaciones';
                    } elseif ($slug === 'diplomado') {
                        $plural_slug = 'diplomados';
                    } elseif ($slug === 'diploma') {
                        $plural_slug = 'diplomas';
                    } elseif (substr($slug, -1) === 'a' || substr($slug, -1) === 'o' || substr($slug, -1) === 'e') {
                        $plural_slug = $slug . 's';
                    } elseif (substr($slug, -1) === 'n') {
                        $plural_slug = $slug . 'es';
                    }
                    
                    $post_link = str_replace('%tipo-oferta-academica%', $plural_slug, $post_link);
                } else {
                    $post_link = str_replace('%tipo-oferta-academica%', 'otros', $post_link);
                }
            }
        }
        return $post_link;
    }
}
