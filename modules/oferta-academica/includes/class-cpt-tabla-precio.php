<?php

if (!defined('ABSPATH')) {
    exit;
}

class CPT_Tabla_Precio {
    public static function init(): void {
        self::register_post_type();
        add_filter('use_block_editor_for_post_type', [self::class, 'disable_block_editor'], 10, 2);
        add_filter('get_edit_post_link', [self::class, 'filter_edit_post_link'], 10, 3);
        add_action('load-post-new.php', [self::class, 'redirect_add_new']);
    }

    public static function disable_block_editor(bool $use_block_editor, string $post_type): bool {
        if ('tabla-precio' === $post_type) {
            return false;
        }

        return $use_block_editor;
    }

    public static function filter_edit_post_link($link, $post_id, $context) {
        if (get_post_type($post_id) === 'tabla-precio') {
            $base_url = get_option('flacso_external_editor_url', 'https://editor-flacso-uy.vercel.app');
            $base_url = rtrim($base_url, '/');

            return esc_url($base_url . '/tablas-precios/' . $post_id);
        }

        return $link;
    }

    public static function redirect_add_new(): void {
        if (isset($_GET['post_type']) && $_GET['post_type'] === 'tabla-precio') {
            $base_url = get_option('flacso_external_editor_url', 'https://editor-flacso-uy.vercel.app');
            $base_url = rtrim($base_url, '/');

            wp_redirect($base_url . '/tablas-precios/nuevo');
            exit;
        }
    }

    public static function register_post_type(): void {
        $labels = [
            'name' => 'Tablas de Precios',
            'singular_name' => 'Tabla de Precio',
            'menu_name' => 'Tablas de Precios',
            'name_admin_bar' => 'Tabla de Precio',
            'add_new' => 'Añadir Nueva',
            'add_new_item' => 'Añadir Nueva Tabla de Precio',
            'new_item' => 'Nueva Tabla de Precio',
            'edit_item' => 'Editar Tabla de Precio',
            'view_item' => 'Ver Tabla de Precio',
            'all_items' => 'Todas las Tablas de Precios',
            'search_items' => 'Buscar Tablas de Precios',
            'not_found' => 'No se encontraron tablas de precios',
            'not_found_in_trash' => 'No hay tablas de precios en la papelera',
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=oferta-academica',
            'show_in_rest' => true,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_icon' => 'dashicons-money-alt',
            'supports' => ['title', 'revisions'],
            'exclude_from_search' => true,
        ];

        register_post_type('tabla-precio', $args);
    }
}
