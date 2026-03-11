<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra las taxonomías del CPT oferta-academica
 */
class Oferta_Taxonomies {
    
    public static function init(): void {
        self::register_taxonomies();
        add_action('init', [__CLASS__, 'create_default_terms'], 20);
    }

    public static function register_taxonomies(): void {
        // Taxonomía: Tipo de Oferta Académica (Maestría, Especialización, Diplomado, Diploma)
        $labels = [
            'name'              => 'Tipo de Oferta',
            'singular_name'     => 'Tipo de Oferta',
            'search_items'      => 'Buscar tipos',
            'all_items'         => 'Todos los tipos',
            'edit_item'         => 'Editar tipo',
            'update_item'       => 'Actualizar tipo',
            'add_new_item'      => 'Añadir nuevo tipo',
            'new_item_name'     => 'Nuevo tipo',
            'menu_name'         => 'Tipo de Oferta',
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'tipo-oferta'],
        ];

        register_taxonomy('tipo-oferta-academica', ['oferta-academica'], $args);

        // Taxonomía: Programa (solo para oferta-academica)
        $labels_area = [
            'name'              => 'Programas',
            'singular_name'     => 'Programa',
            'search_items'      => 'Buscar programas',
            'all_items'         => 'Todos los programas',
            'edit_item'         => 'Editar programa',
            'update_item'       => 'Actualizar programa',
            'add_new_item'      => 'Añadir nuevo programa',
            'new_item_name'     => 'Nuevo programa',
            'menu_name'         => 'Programas',
        ];

        $args_area = [
            'labels'            => $labels_area,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'area-tematica'],
        ];

        register_taxonomy('area_tematica', ['oferta-academica'], $args_area);
    }

    public static function create_default_terms(): void {
        // Términos de tipo-oferta-academica
        $tipos = [
            'Maestría' => 'maestria',
            'Especialización' => 'especializacion',
            'Diplomado' => 'diplomado',
            'Diploma' => 'diploma',
        ];

        foreach ($tipos as $name => $slug) {
            if (!term_exists($slug, 'tipo-oferta-academica')) {
                wp_insert_term($name, 'tipo-oferta-academica', ['slug' => $slug]);
            }
        }

        // Términos de área_tematica
        $areas = [
            'Educación' => 'educacion',
            'Género y Cultura' => 'genero-y-cultura',
            'Infancias y Adolescencias' => 'infancias-y-adolescencias',
            'Producción de Textos' => 'produccion-de-textos',
            'Salud Mental, Subjetividad y Trabajo' => 'salud-mental-subjetividad-y-trabajo',
        ];

        foreach ($areas as $name => $slug) {
            if (!term_exists($slug, 'area_tematica')) {
                wp_insert_term($name, 'area_tematica', ['slug' => $slug]);
            }
        }
    }
}
