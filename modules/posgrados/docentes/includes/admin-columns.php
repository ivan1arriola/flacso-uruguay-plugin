<?php
if (!defined('ABSPATH')) exit;

// Custom columns for docente list (legacy equipo removed).
add_filter('manage_docente_posts_columns', function($columns) {
    return [
        'cb' => $columns['cb'],
        'prefijo' => __('Prefijo'),
        'title' => __('Titulo'),
        'nombre' => __('Nombre'),
        'apellido' => __('Apellido'),
        'date' => $columns['date']
    ];
});

add_action('manage_docente_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'prefijo':
            echo esc_html(get_post_meta($post_id, 'prefijo', true));
            break;
        case 'nombre':
            echo esc_html(get_post_meta($post_id, 'nombre', true));
            break;
        case 'apellido':
            echo esc_html(get_post_meta($post_id, 'apellido', true));
            break;
    }
}, 10, 2);
