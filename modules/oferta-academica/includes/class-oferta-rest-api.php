<?php
if (!defined('ABSPATH')) {
    exit;
}

class Oferta_Rest_API
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_meta_fields']);
    }

    /**
     * Registra todos los campos personalizados en el endpoint estándar de WordPress:
     * wp-json/wp/v2/oferta-academica
     */
    public static function register_meta_fields()
    {
        $meta_keys = [
            'abreviacion', 'correo', 'duracion_meses', 'proximo_inicio', 
            'calendario', 'malla_curricular', 'proximo_inicio_precision', 
            'inscripciones_abiertas', 'modalidad_html', 'duracion_html', 
            'objetivos_html', 'perfil_ingreso_html', 'requisitos_ingreso_html', 
            'malla_curricular_html', 'calendario_html', 'perfil_egreso_html', 
            'requisitos_egreso_html', 'titulos_certificaciones_html',
            'menciones', 'orientaciones', 'coordinacion_academica', 'equipos'
        ];

        foreach ($meta_keys as $key) {
            register_rest_field('oferta-academica', $key, [
                'get_callback' => function ($post_array) use ($key) {
                    return get_post_meta($post_array['id'], $key, true);
                },
                'update_callback' => function ($value, $post_obj) use ($key) {
                    return update_post_meta($post_obj->ID, $key, $value);
                },
                'schema' => null,
            ]);
        }

        // Registrar el ID del post/página asociada (campo legado)
        register_rest_field('oferta-academica', 'associated_post_id', [
            'get_callback' => function ($post_array) {
                return (int) get_post_meta($post_array['id'], '_oferta_page_id', true);
            },
            'update_callback' => function ($value, $post_obj) {
                return update_post_meta($post_obj->ID, '_oferta_page_id', (int) $value);
            },
            'schema' => null,
        ]);

        // Registrar seminarios asociados a la oferta
        register_rest_field('oferta-academica', '_oferta_seminarios_ids', [
            'get_callback' => function ($post_array) {
                $val = get_post_meta($post_array['id'], '_oferta_seminarios_ids', true);
                return is_array($val) ? array_values(array_map('intval', $val)) : [];
            },
            'update_callback' => function ($value, $post_obj) {
                if (is_array($value)) {
                    $cleaned = array_values(array_unique(array_map('intval', $value)));
                    update_post_meta($post_obj->ID, '_oferta_seminarios_ids', $cleaned);
                } else {
                    delete_post_meta($post_obj->ID, '_oferta_seminarios_ids');
                }
                return true;
            },
            'schema' => null,
        ]);

        // Registrar campo para taxonomías simplificadas (mantenemos compatibilidad con el frontend)
        register_rest_field('oferta-academica', 'taxonomies', [
            'get_callback' => function ($post_array) {
                $taxonomies = [
                    'tipo-oferta-academica' => wp_get_post_terms($post_array['id'], 'tipo-oferta-academica', ['fields' => 'all']),
                    'area_tematica' => wp_get_post_terms($post_array['id'], 'area_tematica', ['fields' => 'all']),
                ];
                $tax_simplified = [];
                foreach ($taxonomies as $tax => $terms) {
                    $tax_simplified[$tax] = array_map(function($t) {
                        return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug];
                    }, (array)$terms);
                }
                return $tax_simplified;
            },
            'schema' => null,
        ]);

        // Registrar campo para la imagen destacada enriquecida
        register_rest_field('oferta-academica', 'featured_image_data', [
            'get_callback' => function ($post_array) {
                $media_id = get_post_thumbnail_id($post_array['id']);
                if (!$media_id) return null;
                
                $full = wp_get_attachment_image_src($media_id, 'full');
                $large = wp_get_attachment_image_src($media_id, 'large');
                $medium = wp_get_attachment_image_src($media_id, 'medium');
                $alt = get_post_meta($media_id, '_wp_attachment_image_alt', true);
                
                return [
                    'id' => (int)$media_id,
                    'url' => $full[0] ?? '',
                    'large' => $large[0] ?? '',
                    'medium' => $medium[0] ?? '',
                    'alt' => $alt,
                    'width' => $full[1] ?? 0,
                    'height' => $full[2] ?? 0,
                ];
            },
            'schema' => null,
        ]);
    }
}
