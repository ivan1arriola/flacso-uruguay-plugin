<?php
if (!defined('ABSPATH')) exit;

// Registrar Taxonomía equipo-docente
add_action('init', function() {

    register_taxonomy('equipo-docente', ['docente'], [
        'labels' => [
            'name' => __('Equipo academico'),
            'singular_name' => __('Equipo academico'),
            'menu_name' => __('Equipo academico'),
            'all_items' => __('Todos los equipos'),
            'edit_item' => __('Editar equipo'),
            'view_item' => __('Ver equipo'),
            'update_item' => __('Actualizar equipo'),
            'add_new_item' => __('Agregar equipo'),
            'new_item_name' => __('Nombre del equipo'),
            'search_items' => __('Buscar equipos'),
            'popular_items' => __('Equipos mas usados'),
            'separate_items_with_commas' => __('Separa los equipos con comas'),
            'add_or_remove_items' => __('Agregar o quitar equipos'),
            'choose_from_most_used' => __('Elegir entre los equipos mas usados'),
            'not_found' => __('No se encontraron equipos'),
        ],
        'public' => true,
        'show_ui' => false,
        'show_in_rest' => false,
        'show_admin_column' => false,
        'show_in_quick_edit' => false,
        'meta_box_cb' => false,
        'hierarchical' => false,
        'rewrite' => ['slug' => 'equipo-docente']
    ]);
});
