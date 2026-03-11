<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra el Custom Post Type: docente
 */
class CPT_Docente {
    
    public static function init(): void {
        self::register_post_type();
    }

    public static function register_post_type(): void {
        $labels = [
            'name' => __('Docentes'),
            'singular_name' => __('Docente'),
            'add_new' => __('Añadir nuevo'),
            'add_new_item' => __('Añadir nuevo docente'),
            'edit_item' => __('Editar docente'),
            'new_item' => __('Nuevo docente'),
            'view_item' => __('Ver docente'),
            'all_items' => __('Todos los Docentes'),
            'menu_name' => __('Docentes'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => 'docentes',
            'rewrite' => ['slug' => 'docente'],
            'supports' => ['title', 'thumbnail', 'excerpt', 'custom-fields'],
            'menu_icon' => 'dashicons-welcome-learn-more',
            'menu_position' => 5,
        ];

        register_post_type('docente', $args);
    }
}
