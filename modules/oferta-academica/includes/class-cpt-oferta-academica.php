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
    }

    public static function disable_block_editor(bool $use_block_editor, string $post_type): bool {
        if ('oferta-academica' === $post_type) {
            return false;
        }
        return $use_block_editor;
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
            'rewrite'               => ['slug' => 'programa'],
            'capability_type'       => 'post',
            'has_archive'           => false,
            'hierarchical'          => false,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-welcome-learn-more',
            'supports'              => ['title', 'thumbnail'],
            'taxonomies'            => ['tipo-oferta-academica', 'area_tematica'],
        ];

        register_post_type('oferta-academica', $args);
    }
}
