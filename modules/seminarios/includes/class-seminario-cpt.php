<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_CPT
{
    public static function register()
    {
        $labels = array(
            'name' => 'Seminarios',
            'singular_name' => 'Seminario',
            'add_new' => 'Agregar nuevo',
            'add_new_item' => 'Agregar nuevo seminario',
            'edit_item' => 'Editar seminario',
            'new_item' => 'Nuevo seminario',
            'view_item' => 'Ver seminario',
            'search_items' => 'Buscar seminarios',
            'not_found' => 'No se encontraron seminarios',
            'not_found_in_trash' => 'No se encontraron seminarios en la papelera',
            'all_items' => 'Todos los seminarios',
            'menu_name' => 'Seminarios',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            // Archive and singles under /formacion/seminarios/
            'has_archive' => 'formacion/seminarios',
            'rewrite' => array(
                'slug' => 'formacion/seminarios',
                'with_front' => false,
            ),
            'show_in_rest' => true,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => array('title', 'thumbnail'),
        );

        register_post_type('seminario', $args);
    }
}
