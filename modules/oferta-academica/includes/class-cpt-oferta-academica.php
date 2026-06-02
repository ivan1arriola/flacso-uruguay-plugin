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
        add_filter('use_block_editor_for_post_type', [self::class, 'disable_block_editor'], 10, 2);
        add_filter('get_edit_post_link', [self::class, 'filter_edit_post_link'], 10, 3);
        add_action('admin_bar_menu', [self::class, 'modify_admin_bar_edit_link'], 100);
        add_action('load-post-new.php', [self::class, 'redirect_add_new']);
        add_action('wp_head', [self::class, 'add_og_meta_tags'], 5);
    }

    public static function disable_block_editor(bool $use_block_editor, string $post_type): bool {
        if ('oferta-academica' === $post_type) {
            return false;
        }
        return $use_block_editor;
    }

    public static function filter_edit_post_link($link, $post_id, $context) {
        if (get_post_type($post_id) === 'oferta-academica') {
            $base_url = get_option('flacso_external_editor_url', 'https://editor-flacso-uy.vercel.app');
            $base_url = rtrim($base_url, '/');
            return esc_url($base_url . '/ofertas/' . $post_id);
        }
        return $link;
    }

    public static function modify_admin_bar_edit_link(WP_Admin_Bar $wp_admin_bar): void {
        if (!is_singular('oferta-academica')) {
            return;
        }
        
        $post_id = get_the_ID();
        $base_url = get_option('flacso_external_editor_url', 'https://editor-flacso-uy.vercel.app');
        $base_url = rtrim($base_url, '/');
        $edit_url = esc_url($base_url . '/ofertas/' . $post_id);

        $node = $wp_admin_bar->get_node('edit');
        if ($node) {
            $node->href = $edit_url;
            $wp_admin_bar->add_node($node);
        }
    }

    public static function redirect_add_new() {
        if (isset($_GET['post_type']) && $_GET['post_type'] === 'oferta-academica') {
            $base_url = get_option('flacso_external_editor_url', 'https://editor-flacso-uy.vercel.app');
            $base_url = rtrim($base_url, '/');
            wp_redirect($base_url . '/ofertas/new');
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
