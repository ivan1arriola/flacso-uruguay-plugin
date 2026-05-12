<?php
if (!defined('ABSPATH')) {
    exit;
}

class Oferta_Rest_API
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes()
    {
        // Endpoint para obtener taxonomías (REGISTRAR ANTES DE LOS IDS PARA EVITAR CONFLICTOS)
        register_rest_route('flacso/v1', '/oferta-academica/taxonomies', array(
            'methods' => 'GET',
            'callback' => [self::class, 'get_taxonomies'],
            'permission_callback' => '__return_true'
        ));

        // Endpoint para el listado
        register_rest_route('flacso/v1', '/oferta-academica', array(
            'methods' => 'GET',
            'callback' => [self::class, 'get_collection'],
            'permission_callback' => '__return_true'
        ));

        // Endpoint para un ítem individual
        register_rest_route('flacso/v1', '/oferta-academica/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => [self::class, 'get_item'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ));

        // Endpoint para actualizar
        register_rest_route('flacso/v1', '/oferta-academica/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => [self::class, 'update_item'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ));
    }

    public static function get_collection($request)
    {
        $query = new WP_Query(array(
            'post_type'      => 'oferta-academica',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $items = array();
        foreach ($query->posts as $post) {
            $items[] = self::prepare_item_for_response($post);
        }

        return new WP_REST_Response($items, 200);
    }

    public static function get_item($request)
    {
        $post = get_post((int) $request['id']);
        if (!$post || $post->post_type !== 'oferta-academica') {
            return new WP_Error('oferta_not_found', 'Oferta no encontrada', array('status' => 404));
        }

        return new WP_REST_Response(self::prepare_item_for_response($post), 200);
    }

    public static function get_taxonomies()
    {
        return new WP_REST_Response([
            'tipo-oferta-academica' => self::get_terms_simplified('tipo-oferta-academica'),
            'area_tematica' => self::get_terms_simplified('area_tematica'),
        ], 200);
    }

    private static function get_terms_simplified($taxonomy)
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        if (is_wp_error($terms)) return [];
        
        return array_map(function($term) {
            return [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug
            ];
        }, $terms);
    }

    public static function update_item($request)
    {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'oferta-academica') {
            return new WP_Error('oferta_not_found', 'Oferta no encontrada', array('status' => 404));
        }

        $params = $request->get_params();

        // Actualizar datos básicos
        $update_data = array('ID' => $post_id);
        if (isset($params['title'])) {
            $update_data['post_title'] = sanitize_text_field($params['title']);
        }
        if (isset($params['status'])) {
            $update_data['post_status'] = sanitize_text_field($params['status']);
        }
        if (isset($params['slug'])) {
            $update_data['post_name'] = sanitize_title($params['slug']);
        }

        wp_update_post($update_data);

        // Actualizar metadatos
        if (isset($params['meta']) && is_array($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                if (is_array($value)) {
                    update_post_meta($post_id, $key, $value);
                } else {
                    update_post_meta($post_id, $key, wp_kses_post($value));
                }
            }
        }

        // Actualizar taxonomías
        if (isset($params['taxonomies']) && is_array($params['taxonomies'])) {
            foreach ($params['taxonomies'] as $taxonomy => $term_ids) {
                wp_set_post_terms($post_id, array_map('intval', (array)$term_ids), $taxonomy);
            }
        }

        // Actualizar imagen destacada
        if (isset($params['featured_media'])) {
            set_post_thumbnail($post_id, (int) $params['featured_media']);
        }

        return new WP_REST_Response(self::prepare_item_for_response(get_post($post_id)), 200);
    }

    private static function prepare_item_for_response($post)
    {
        $featured_media_id = get_post_thumbnail_id($post->ID);
        $featured_image_url = $featured_media_id ? get_the_post_thumbnail_url($post->ID, 'large') : '';

        // Metadatos definidos en Oferta_Data_MetaBox
        $meta = array();
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
            $value = get_post_meta($post->ID, $key, true);
            $meta[$key] = $value;
        }

        // Taxonomías
        $taxonomies = [
            'tipo-oferta-academica' => wp_get_post_terms($post->ID, 'tipo-oferta-academica', ['fields' => 'all']),
            'area_tematica' => wp_get_post_terms($post->ID, 'area_tematica', ['fields' => 'all']),
        ];

        // Simplificar taxonomías para el frontend
        $tax_simplified = [];
        foreach ($taxonomies as $tax => $terms) {
            $tax_simplified[$tax] = array_map(function($t) {
                return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug];
            }, (array)$terms);
        }

        return array(
            'id' => $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'featured_media' => (int) $featured_media_id,
            'featured_image' => $featured_image_url,
            'url' => get_permalink($post),
            'meta' => $meta,
            'taxonomies' => $tax_simplified
        );
    }
}
